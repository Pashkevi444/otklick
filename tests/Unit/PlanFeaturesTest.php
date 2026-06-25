<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Shared\DTO\PlanFeatures;
use App\Shared\Enums\TenantPlan;
use App\Shared\Models\Tenant;
use Tests\TestCase;

final class PlanFeaturesTest extends TestCase
{
    public function test_merge_overrides_only_present_keys(): void
    {
        $base = new PlanFeatures(
            maxOperators: 2, crm: false, analytics: false, broadcasts: false, flows: false,
            clientBase: false, allChannels: false, webWidget: true,
            maxNotifyEmail: 1, maxNotifyTelegram: 4,
        );

        $merged = $base->merge(['crm' => true, 'maxNotifyEmail' => 5]);

        $this->assertTrue($merged->crm);
        $this->assertSame(5, $merged->maxNotifyEmail);
        $this->assertSame(4, $merged->maxNotifyTelegram); // не переопределяли
        $this->assertFalse($merged->analytics);
    }

    public function test_empty_overrides_keep_base(): void
    {
        $base = TenantPlan::Standard->features();

        $this->assertEquals($base, $base->merge([]));
    }

    public function test_tenant_effective_features_apply_overrides(): void
    {
        $tenant = new Tenant([
            'plan' => TenantPlan::Standard,
            'settings' => ['overrides' => ['crm' => true, 'maxNotifyEmail' => 9]],
        ]);

        $this->assertTrue($tenant->features()->crm);
        $this->assertSame(9, $tenant->features()->maxNotifyEmail);
    }

    public function test_tenant_without_overrides_uses_plan(): void
    {
        $tenant = new Tenant(['plan' => TenantPlan::Standard]);

        $this->assertFalse($tenant->features()->crm);
        $this->assertSame(1, $tenant->features()->maxNotifyEmail);
        $this->assertSame(4, $tenant->features()->maxNotifyTelegram);
    }
}
