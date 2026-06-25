<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTO;

use Carbon\CarbonInterface;

/**
 * Запись кода подтверждения смены e-mail (из email_change_codes).
 */
final readonly class EmailChangeCodeData
{
    public function __construct(
        public string $newEmail,
        public string $hashedCode,
        public CarbonInterface $createdAt,
    ) {}
}
