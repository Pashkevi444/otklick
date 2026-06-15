<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTO\PasswordResetCodeData;

/**
 * Доступ к одноразовым кодам восстановления пароля (таблица password_reset_tokens).
 * Не тенант-данные — общий механизм аутентификации.
 */
interface PasswordResetCodeRepositoryInterface
{
    /**
     * Сохранить (перезаписать) хеш кода для email с текущей меткой времени.
     */
    public function put(string $email, string $hashedCode): void;

    public function get(string $email): ?PasswordResetCodeData;

    public function delete(string $email): void;
}
