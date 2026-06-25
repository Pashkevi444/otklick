<?php

declare(strict_types=1);

namespace App\Modules\Booking\DTO;

use App\Shared\Enums\CrmProvider;

/**
 * Данные для подключения тенанта к CRM. Креды — обобщённая карта (ключи зависят
 * от провайдера и описаны в его стратегии), поэтому DTO не знает о конкретной CRM.
 */
final readonly class NewCrmConnectionData
{
    public function __construct(
        public string $tenantId,
        public CrmProvider $provider,
        /** @var array<string, string> */
        public array $credentials,
        /** @var array<string, mixed> */
        public array $settings = [],
    ) {}
}
