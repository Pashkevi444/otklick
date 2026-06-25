<?php

declare(strict_types=1);

namespace App\Modules\Identity\Repositories\Contracts;

use App\Modules\Identity\DTO\EmailChangeCodeData;

/**
 * Доступ к кодам подтверждения смены e-mail (таблица email_change_codes).
 * Не тенант-данные — общий механизм аккаунта.
 */
interface EmailChangeCodeRepositoryInterface
{
    public function put(int $userId, string $newEmail, string $hashedCode): void;

    public function get(int $userId): ?EmailChangeCodeData;

    public function delete(int $userId): void;
}
