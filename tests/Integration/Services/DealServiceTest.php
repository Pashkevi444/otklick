<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\DTO\DealData;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Tenant;
use App\Services\DealService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DealServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($this->tenant->id);
    }

    private function service(): DealService
    {
        return $this->app->make(DealService::class);
    }

    public function test_ensure_stages_seeds_default_funnel_once(): void
    {
        $this->service()->ensureStages();
        $this->assertSame(5, DealStage::query()->count());

        $this->service()->ensureStages();
        $this->assertSame(5, DealStage::query()->count());
    }

    public function test_create_deal_in_first_stage(): void
    {
        $stageId = $this->service()->firstStageId();
        $this->assertNotNull($stageId);

        $deal = $this->service()->create(new DealData(stageId: $stageId, title: 'Сделка', value: 1000));

        $this->assertSame('Сделка', $deal->title);
        $this->assertSame(1000, $deal->value);
        $this->assertSame(1, Deal::query()->count());
    }
}
