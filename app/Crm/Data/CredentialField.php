<?php

declare(strict_types=1);

namespace App\Crm\Data;

/**
 * Описание поля кредов, которое требует CRM-стратегия для подключения.
 * По этим метаданным строятся валидация и форма в кабинете — без знания о
 * конкретной CRM в контроллере/сервисе.
 */
final readonly class CredentialField
{
    public function __construct(
        public string $key,
        public string $label,
        public bool $secret = false,
    ) {}
}
