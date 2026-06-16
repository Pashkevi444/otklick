<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\NotificationChannelType;
use App\Models\NotificationRecipient;
use Illuminate\Support\Collection;

/**
 * Доступ к получателям уведомлений текущего тенанта (scoped/RLS).
 */
interface NotificationRecipientRepositoryInterface
{
    /**
     * @return Collection<int, NotificationRecipient>
     */
    public function forCurrentTenant(): Collection;

    /**
     * Готовые к доставке (активны и подтверждены) — для рассылки уведомлений.
     *
     * @return Collection<int, NotificationRecipient>
     */
    public function deliverableForCurrentTenant(): Collection;

    /**
     * Сколько получателей данного канала уже заведено (для проверки лимита тарифа).
     */
    public function countByType(NotificationChannelType $type): int;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): NotificationRecipient;

    public function findForCurrentTenant(string $id): ?NotificationRecipient;

    public function findByLinkToken(string $token): ?NotificationRecipient;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(NotificationRecipient $recipient, array $attributes): void;

    public function delete(NotificationRecipient $recipient): void;
}
