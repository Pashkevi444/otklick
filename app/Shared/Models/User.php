<?php

namespace App\Shared\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Shared\Enums\CabinetSection;
use App\Shared\Enums\MemberPermission;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Concerns\BelongsToTenant;
use App\Shared\Tenancy\Contracts\TenantOwned;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property string|null $tenant_id
 * @property UserRole $role
 * @property list<string>|null $permissions
 * @property string|null $two_factor_secret
 * @property array<int, string>|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 */
#[Fillable(['tenant_id', 'name', 'email', 'password', 'role', 'permissions'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements TenantOwned
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'permissions' => 'array',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    /**
     * Доступен ли раздел кабинета. Владелец и супер-админ — без ограничений;
     * сотруднику доступны только разделы из его permissions.
     */
    public function allows(string $section): bool
    {
        if ($this->role === UserRole::Owner || $this->role === UserRole::SuperAdmin) {
            return true;
        }

        return in_array($section, $this->permissions ?? [], true);
    }

    /**
     * Эффективный список доступных разделов (для UI: владельцу — все).
     *
     * @return list<string>
     */
    public function allowedSections(): array
    {
        return $this->role === UserRole::Owner || $this->role === UserRole::SuperAdmin
            ? CabinetSection::values()
            : ($this->permissions ?? []);
    }

    /**
     * Эффективные права для фронта (разделы + права-действия): владельцу/СУ — все,
     * сотруднику — его permissions. Фронт по ним показывает/прячет кнопки
     * (редактирование/удаление в гридах).
     *
     * @return list<string>
     */
    public function effectivePermissions(): array
    {
        return $this->role === UserRole::Owner || $this->role === UserRole::SuperAdmin
            ? MemberPermission::values()
            : ($this->permissions ?? []);
    }

    /**
     * 2FA реально включена (секрет задан и подтверждён вводом кода).
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }
}
