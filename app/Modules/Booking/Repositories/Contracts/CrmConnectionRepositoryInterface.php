<?php

declare(strict_types=1);

namespace App\Modules\Booking\Repositories\Contracts;

use App\Modules\Booking\DTO\NewCrmConnectionData;
use App\Modules\Booking\Models\CrmConnection;
use App\Shared\Enums\CrmProvider;
use Illuminate\Support\Collection;

/**
 * Контракт доступа к данным CRM-подключений. Скоупится текущим тенантом.
 */
interface CrmConnectionRepositoryInterface
{
    public function create(NewCrmConnectionData $data): CrmConnection;

    public function find(string $id): ?CrmConnection;

    public function findByProviderForCurrentTenant(CrmProvider $provider): ?CrmConnection;

    /**
     * Первое активное CRM-подключение текущего тенанта (любой провайдер) или
     * null. Нужно автозаписи, чтобы понять, доступна ли запись в CRM.
     */
    public function activeForCurrentTenant(): ?CrmConnection;

    /**
     * @return Collection<int, CrmConnection>
     */
    public function forCurrentTenant(): Collection;

    /**
     * @param  array<string, mixed>  $settings
     */
    public function updateSettings(CrmConnection $connection, array $settings): void;

    public function delete(CrmConnection $connection): void;
}
