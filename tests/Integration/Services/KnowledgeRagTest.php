<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Models\KnowledgeEntry;
use App\Models\Tenant;
use App\Services\KnowledgeIndexer;
use App\Services\KnowledgeRetriever;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class KnowledgeRagTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($this->tenant->id);
    }

    private function entry(string $title, string $content): KnowledgeEntry
    {
        return KnowledgeEntry::query()->create([
            'tenant_id' => $this->tenant->id,
            'title' => $title,
            'content' => $content,
            'is_published' => true,
        ]);
    }

    public function test_indexes_published_entries(): void
    {
        $this->entry('Стрижка', 'Мужская стрижка стоит 1500 рублей');
        $this->entry('Доставка', 'Доставка пиццы бесплатно');

        $this->app->make(KnowledgeIndexer::class)->reindex();

        $this->assertSame(2, DB::table('knowledge_chunks')->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_retrieves_relevant_entry_for_question(): void
    {
        $haircut = $this->entry('Стрижка', 'Мужская стрижка стоит 1500 рублей');
        $this->entry('Доставка', 'Доставка пиццы бесплатно');

        $this->app->make(KnowledgeIndexer::class)->reindex();

        $result = $this->app->make(KnowledgeRetriever::class)->retrieve('сколько стоит стрижка', 1);

        $this->assertNotNull($result);
        $this->assertSame([$haircut->id], $result['manual']);
    }

    public function test_returns_null_when_index_empty(): void
    {
        $result = $this->app->make(KnowledgeRetriever::class)->retrieve('что угодно', 5);

        $this->assertNull($result);
    }
}
