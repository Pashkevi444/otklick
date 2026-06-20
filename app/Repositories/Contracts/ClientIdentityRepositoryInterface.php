<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\ChannelType;

/**
 * Доступ к нативным идентичностям клиентов по каналам. Скоупится текущим
 * тенантом (RLS + scope). Дедуп — по `(channel_type, identity)` в пределах тенанта.
 */
interface ClientIdentityRepositoryInterface
{
    /** id клиента по нативному идентификатору канала, либо null. */
    public function findClientId(ChannelType $type, string $identity): ?string;

    /** Привязывает (создаёт/обновляет) идентичность канала к клиенту. */
    public function link(string $clientId, ChannelType $type, string $identity): void;
}
