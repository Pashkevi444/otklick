<?php

declare(strict_types=1);

namespace App\Shared\Tenancy;

/**
 * Хранит идентификатор текущего тенанта в рамках одного запроса.
 *
 * Биндится в контейнер как scoped — Octane сбрасывает scoped-инстансы между
 * запросами, поэтому состояние тенанта не «протекает» между запросами в
 * резидентном рантайме (требование безопасности при Octane).
 */
final class TenantContext
{
    private ?string $tenantId = null;

    public function set(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function id(): ?string
    {
        return $this->tenantId;
    }

    public function has(): bool
    {
        return $this->tenantId !== null;
    }

    public function forget(): void
    {
        $this->tenantId = null;
    }
}
