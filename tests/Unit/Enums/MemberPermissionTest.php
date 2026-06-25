<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Shared\Enums\CabinetSection;
use App\Shared\Enums\MemberPermission;
use App\Shared\Enums\TenantPlan;
use Tests\TestCase;

final class MemberPermissionTest extends TestCase
{
    public function test_every_section_has_a_matching_access_permission(): void
    {
        // Защита от рассинхрона: каждый раздел кабинета представлен правом-доступом.
        foreach (CabinetSection::cases() as $section) {
            $this->assertNotNull(
                MemberPermission::tryFrom($section->value),
                "Раздел «{$section->value}» отсутствует в MemberPermission",
            );
        }
    }

    public function test_grantable_is_bounded_by_plan_features(): void
    {
        $trial = TenantPlan::Trial->features();
        $grantable = array_map(fn (MemberPermission $p): string => $p->value, MemberPermission::grantableWith($trial));

        // Без clientBase/analytics/crm — этих прав нет в выдаче.
        $this->assertNotContains('clients', $grantable);
        $this->assertNotContains('clients.delete', $grantable);
        $this->assertNotContains('analytics', $grantable);
        $this->assertNotContains('integrations', $grantable);
        $this->assertNotContains('scenarios', $grantable);
        // Базовые — есть.
        $this->assertContains('conversations', $grantable);
        $this->assertContains('conversations.delete', $grantable);
        // Тестирование бота доступно на всех тарифах (без требования к возможности).
        $this->assertContains('testing', $grantable);

        // На «Макс» доступно всё.
        $max = array_map(fn (MemberPermission $p): string => $p->value, MemberPermission::grantableWith(TenantPlan::Max->features()));
        $this->assertContains('clients.delete', $max);
        $this->assertContains('scenarios', $max);
    }
}
