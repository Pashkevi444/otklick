<?php

declare(strict_types=1);

namespace App\DTO;

use Carbon\CarbonInterface;

/**
 * Запись кода восстановления пароля (из password_reset_tokens).
 */
final readonly class PasswordResetCodeData
{
    public function __construct(
        public string $hashedCode,
        public CarbonInterface $createdAt,
    ) {}
}
