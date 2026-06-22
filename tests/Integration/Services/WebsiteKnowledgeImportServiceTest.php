<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Llm\Contracts\LlmClient;
use App\Models\KnowledgeEntry;
use App\Models\Tenant;
use App\Services\WebsiteKnowledgeImportService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class WebsiteKnowledgeImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($this->tenant->id);
    }

    /**
     * Стаб LLM: на каждый вызов отдаёт два элемента с уникальными заголовками
     * (счётчик), чтобы дедуп по заголовку не схлопывал страницы.
     */
    private function stubLlm(): void
    {
        $this->app->instance(LlmClient::class, new class implements LlmClient
        {
            private int $calls = 0;

            public function generate(string $systemPrompt, array $messages): string
            {
                $this->calls++;

                return (string) json_encode([
                    ['title' => "Услуга {$this->calls}", 'content' => 'Готовый ответ клиенту про услугу.'],
                    ['title' => "Вопрос {$this->calls}", 'content' => 'Ответ на частый вопрос.'],
                ], JSON_UNESCAPED_UNICODE);
            }
        });
    }

    private function fakeSite(): void
    {
        $html = '<html><body>'
            .'<a href="/uslugi">Услуги</a> <a href="/contacts">Контакты</a> '
            .str_repeat('Подробный текст о наших услугах, ценах и условиях работы. ', 20)
            .'</body></html>';

        Http::fake(['*' => Http::response($html, 200)]);
    }

    public function test_imports_site_into_draft_entries(): void
    {
        $this->fakeSite();
        $this->stubLlm();

        $created = $this->app->make(WebsiteKnowledgeImportService::class)->import('https://mysite.ru');

        $this->assertGreaterThan(0, $created);
        $this->assertSame($created, KnowledgeEntry::query()->count());

        // Всё — черновики: бизнес сам решает, что опубликовать.
        $this->assertSame(0, KnowledgeEntry::query()->where('is_published', true)->count());
        $this->assertDatabaseHas('knowledge_entries', ['title' => 'Услуга 1', 'is_published' => false]);
    }

    public function test_reports_progress(): void
    {
        $this->fakeSite();
        $this->stubLlm();

        $percents = [];
        $this->app->make(WebsiteKnowledgeImportService::class)
            ->import('https://mysite.ru', function (int $percent, int $created) use (&$percents): void {
                $percents[] = $percent;
            });

        $this->assertNotEmpty($percents);
        $this->assertLessThanOrEqual(100, max($percents));
        $this->assertGreaterThan(0, max($percents));
    }

    public function test_bare_domain_is_normalized_and_fetched(): void
    {
        $this->fakeSite();
        $this->stubLlm();

        $this->app->make(WebsiteKnowledgeImportService::class)->import('mysite.ru');

        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://mysite.ru'));
    }

    public function test_unreachable_site_creates_nothing(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $this->stubLlm();

        $created = $this->app->make(WebsiteKnowledgeImportService::class)->import('https://down.ru');

        $this->assertSame(0, $created);
        $this->assertSame(0, KnowledgeEntry::query()->count());
    }

    public function test_skips_garbage_llm_output(): void
    {
        $this->fakeSite();
        $this->app->instance(LlmClient::class, new class implements LlmClient
        {
            public function generate(string $systemPrompt, array $messages): string
            {
                return 'это не json';
            }
        });

        $created = $this->app->make(WebsiteKnowledgeImportService::class)->import('https://mysite.ru');

        $this->assertSame(0, $created);
    }
}
