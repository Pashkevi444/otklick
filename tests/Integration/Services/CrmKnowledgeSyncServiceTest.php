<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Modules\Booking\Crm\CrmGatewayResolver;
use App\Modules\Booking\Crm\Data\CrmCompany;
use App\Modules\Booking\Crm\Data\CrmService;
use App\Modules\Booking\Crm\Data\CrmStaff;
use App\Modules\Booking\Models\CrmConnection;
use App\Modules\Knowledge\Models\CrmKnowledgeEntry;
use App\Modules\Knowledge\Models\KnowledgeEntry;
use App\Modules\Knowledge\Services\CrmKnowledgeSyncService;
use App\Shared\Models\Tenant;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeCrmGateway;
use Tests\TestCase;

final class CrmKnowledgeSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private FakeCrmGateway $crm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($this->tenant->id);
        CrmConnection::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->crm = new FakeCrmGateway;
        $this->crm->services = [new CrmService('s1', 'Стрижка', price: 1500, durationMinutes: 45)];
        $this->crm->staff = [new CrmStaff('m1', 'Савелий', 'барбер')];
        $this->crm->company = new CrmCompany('Барбершоп', 'Ленина 1', '+7 999');
        $this->app->instance(CrmGatewayResolver::class, new CrmGatewayResolver([$this->crm]));
    }

    private function sync(): void
    {
        $this->app->make(CrmKnowledgeSyncService::class)->sync();
    }

    public function test_syncs_services_staff_and_company(): void
    {
        $this->sync();

        $this->assertDatabaseHas('crm_knowledge_entries', ['category' => 'service', 'title' => 'Стрижка']);
        $this->assertDatabaseHas('crm_knowledge_entries', ['category' => 'staff', 'title' => 'Савелий']);
        $this->assertDatabaseHas('crm_knowledge_entries', ['category' => 'company', 'title' => 'Барбершоп']);

        $service = CrmKnowledgeEntry::query()->where('category', 'service')->firstOrFail();
        $this->assertStringContainsString('1500 ₽', $service->content);
        $this->assertStringContainsString('45 мин', $service->content);
    }

    public function test_resync_replaces_previous_crm_data(): void
    {
        $this->sync();
        $this->assertSame(3, CrmKnowledgeEntry::query()->count());

        // Услугу переименовали и убрали мастера — повторная выгрузка отражает это.
        $this->crm->services = [new CrmService('s1', 'Мужская стрижка', price: 1700)];
        $this->crm->staff = [];
        $this->sync();

        $this->assertSame(2, CrmKnowledgeEntry::query()->count()); // услуга + филиал
        $this->assertDatabaseHas('crm_knowledge_entries', ['title' => 'Мужская стрижка']);
        $this->assertDatabaseMissing('crm_knowledge_entries', ['title' => 'Стрижка']);
        $this->assertDatabaseMissing('crm_knowledge_entries', ['title' => 'Савелий']);
    }

    public function test_reports_progress_up_to_100(): void
    {
        $seen = [];
        $this->app->make(CrmKnowledgeSyncService::class)->sync(function (int $p) use (&$seen): void {
            $seen[] = $p;
        });

        $this->assertNotEmpty($seen);
        $this->assertSame(100, end($seen));
    }

    public function test_client_knowledge_base_is_untouched(): void
    {
        KnowledgeEntry::query()->create(['tenant_id' => $this->tenant->id, 'title' => 'Моя заметка', 'content' => 'текст', 'is_published' => true]);

        $this->sync();

        $this->assertDatabaseHas('knowledge_entries', ['title' => 'Моя заметка']);
        $this->assertSame(1, KnowledgeEntry::query()->count());
    }
}
