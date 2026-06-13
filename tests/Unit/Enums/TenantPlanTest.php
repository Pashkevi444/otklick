<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\TenantPlan;
use PHPUnit\Framework\TestCase;

final class TenantPlanTest extends TestCase
{
    public function test_every_case_has_a_non_empty_human_label(): void
    {
        foreach (TenantPlan::cases() as $plan) {
            $this->assertNotSame('', $plan->label());
        }
    }

    public function test_label_returns_russian_text(): void
    {
        $this->assertSame('Пробный', TenantPlan::Trial->label());
        $this->assertSame('Стартовый', TenantPlan::Starter->label());
        $this->assertSame('Профи', TenantPlan::Pro->label());
    }

    public function test_default_plan_for_new_tenant_is_trial(): void
    {
        $this->assertSame(TenantPlan::Trial, TenantPlan::default());
    }

    public function test_is_backed_by_string_value(): void
    {
        $this->assertSame('trial', TenantPlan::Trial->value);
        $this->assertSame(TenantPlan::Pro, TenantPlan::from('pro'));
    }
}
