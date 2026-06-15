<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\UserRole;

/**
 * Данные для создания пользователя. Переносятся между сервисом и репозиторием.
 * tenant_id = null для супер-админа (кросс-тенантный пользователь).
 */
final readonly class NewUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public UserRole $role,
        public ?string $tenantId = null,
    ) {}
}
