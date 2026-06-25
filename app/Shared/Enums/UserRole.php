<?php

declare(strict_types=1);

namespace App\Shared\Enums;

use App\Shared\Enums\Contracts\HasLabel;

/**
 * Роль пользователя.
 *
 * - SuperAdmin — оператор SaaS (tenant_id = null), кросс-тенантный доступ;
 * - Owner — владелец бизнеса-тенанта;
 * - Member — сотрудник бизнеса.
 */
enum UserRole: string implements HasLabel
{
    case SuperAdmin = 'super_admin';
    case Owner = 'owner';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Супер-админ',
            self::Owner => 'Владелец',
            self::Member => 'Сотрудник',
        };
    }

    /**
     * Роль по умолчанию для нового пользователя тенанта.
     */
    public static function default(): self
    {
        return self::Member;
    }
}
