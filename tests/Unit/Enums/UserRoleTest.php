<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Shared\Enums\UserRole;
use PHPUnit\Framework\TestCase;

final class UserRoleTest extends TestCase
{
    public function test_every_case_has_a_non_empty_label(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertNotSame('', $role->label());
        }
    }

    public function test_values(): void
    {
        $this->assertSame('super_admin', UserRole::SuperAdmin->value);
        $this->assertSame('owner', UserRole::Owner->value);
        $this->assertSame('member', UserRole::Member->value);
    }

    public function test_default_role_is_member(): void
    {
        $this->assertSame(UserRole::Member, UserRole::default());
    }
}
