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
        $this->assertSame('Стандарт', TenantPlan::Standard->label());
        $this->assertSame('Макс', TenantPlan::Max->label());
    }

    public function test_default_plan_for_new_tenant_is_trial(): void
    {
        $this->assertSame(TenantPlan::Trial, TenantPlan::default());
    }

    public function test_is_backed_by_string_value(): void
    {
        $this->assertSame('trial', TenantPlan::Trial->value);
        $this->assertSame(TenantPlan::Max, TenantPlan::from('max'));
        $this->assertSame(TenantPlan::Standard, TenantPlan::from('standard'));
    }

    public function test_trial_inherits_standard_tier(): void
    {
        $this->assertSame(TenantPlan::Standard, TenantPlan::Trial->tier());
        $this->assertSame(TenantPlan::Standard, TenantPlan::Standard->tier());
        $this->assertSame(TenantPlan::Max, TenantPlan::Max->tier());
    }

    public function test_crm_and_premium_features_only_on_max(): void
    {
        $this->assertFalse(TenantPlan::Trial->features()->crm);
        $this->assertFalse(TenantPlan::Standard->features()->crm);
        $this->assertTrue(TenantPlan::Max->features()->crm);

        $this->assertSame(2, TenantPlan::Standard->features()->maxOperators);
        $this->assertSame(10, TenantPlan::Max->features()->maxOperators); // удвоено

        // Виджет на сайт — на всех тарифах.
        $this->assertTrue(TenantPlan::Trial->features()->webWidget);
        $this->assertTrue(TenantPlan::Max->features()->webWidget);
    }

    public function test_reminders_only_on_premium(): void
    {
        $this->assertFalse(TenantPlan::Standard->features()->reminders);
        $this->assertTrue(TenantPlan::Max->features()->reminders);
        $this->assertTrue(TenantPlan::Individual->features()->reminders);
    }

    public function test_individual_includes_everything_with_bigger_limits(): void
    {
        $f = TenantPlan::Individual->features();

        $this->assertTrue($f->crm && $f->analytics && $f->allChannels);
        $this->assertGreaterThan(TenantPlan::Max->features()->maxOperators, $f->maxOperators);
    }

    public function test_prices(): void
    {
        $this->assertSame(0, TenantPlan::Trial->priceRub());
        $this->assertSame(9900, TenantPlan::Standard->priceRub());
        $this->assertSame(14900, TenantPlan::Max->priceRub());
        $this->assertSame(0, TenantPlan::Individual->priceRub()); // по договорённости
    }
}
