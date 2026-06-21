<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Домашний маршрут пользователя после входа — зависит от роли.
 */
final class HomeRedirect
{
    public static function for(User $user): string
    {
        return $user->isSuperAdmin()
            ? route('admin.dashboard')
            : route('cabinet.dashboard');
    }
}
