<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTO;

use App\Shared\Enums\UserRole;

/**
 * Данные для создания пользователя. Переносятся между сервисом и репозиторием.
 * tenant_id = null для супер-админа (кросс-тенантный пользователь).
 */
final readonly class NewUserData
{
    /**
     * @param  list<string>  $permissions  доступные разделы кабинета (для сотрудника)
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public UserRole $role,
        public ?string $tenantId = null,
        public array $permissions = [],
    ) {}
}
