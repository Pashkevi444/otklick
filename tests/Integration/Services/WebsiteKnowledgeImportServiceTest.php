<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Modules\Knowledge\Models\KnowledgeEntry;
use App\Modules\Knowledge\Services\WebsiteKnowledgeImportService;
use App\Shared\Llm\Contracts\LlmClient;
use App\Shared\Models\Tenant;
use App\Shared\Tenancy\TenantContext;
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

    public function test_extracts_content_from_spa_shell_via_jsonld_and_state(): void
    {
        // SPA без SSR: видимого текста почти нет, но контент лежит в JSON-LD и
        // во встроенном состоянии (Inertia data-page) — парсер должен его достать.
        $ld = (string) json_encode([
            '@type' => 'Organization',
            'name' => 'Барбершоп Метрополь',
            'description' => 'Мужские стрижки, моделирование бороды, бритьё опасной бритвой и детские '
                .'стрижки. Работаем ежедневно с 10:00 до 22:00 в самом центре города у метро.',
        ], JSON_UNESCAPED_UNICODE);
        $page = htmlspecialchars((string) json_encode([
            'props' => [
                'hero' => 'Стрижка от 1500 рублей, запись онлайн круглосуточно без выходных каждый день.',
                'about' => 'Опытные барберы, авторские стрижки, кофе в подарок каждому гостю салона.',
            ],
        ], JSON_UNESCAPED_UNICODE), ENT_QUOTES);

        $html = '<html><head><title>Барбершоп</title>'
            .'<script type="application/ld+json">'.$ld.'</script></head>'
            .'<body><div id="app" data-page="'.$page.'"></div></body></html>';

        Http::fake(['*' => Http::response($html, 200)]);

        // LLM, который реагирует на наличие текста (возвращает запись, если вход непустой).
        $this->app->instance(LlmClient::class, new class implements LlmClient
        {
            public function generate(string $systemPrompt, array $messages): string
            {
                $text = $messages[0]['content'] ?? '';

                return str_contains($text, 'Метрополь') || str_contains($text, 'Стрижка')
                    ? (string) json_encode([['title' => 'О нас', 'content' => 'Барбершоп в центре.']], JSON_UNESCAPED_UNICODE)
                    : '[]';
            }
        });

        $created = $this->app->make(WebsiteKnowledgeImportService::class)->import('https://spa.ru');

        $this->assertGreaterThan(0, $created);
    }

    public function test_discovers_subpages_via_sitemap(): void
    {
        // Корень без ссылок (как SPA), но подстраницы перечислены в sitemap.xml.
        $body = '<html><body>'.str_repeat('Текст страницы про услуги и цены салона. ', 20).'</body></html>';

        Http::fake([
            'https://shop.ru/sitemap.xml' => Http::response(
                '<?xml version="1.0"?><urlset><url><loc>https://shop.ru/uslugi</loc></url>'
                .'<url><loc>https://shop.ru/contacts</loc></url></urlset>',
                200,
                ['Content-Type' => 'application/xml'],
            ),
            '*' => Http::response($body, 200),
        ]);
        $this->stubLlm();

        $this->app->make(WebsiteKnowledgeImportService::class)->import('https://shop.ru');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://shop.ru/uslugi');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://shop.ru/contacts');
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
