<?php

declare(strict_types=1);

namespace App\Modules\Broadcasts\DTO;

use App\Shared\Enums\BroadcastRecurrence;
use Illuminate\Support\Carbon;

/**
 * Данные рассылки из кабинета (Controller → Service). channels — подмножество
 * {telegram, vk, max, email}.
 */
final readonly class BroadcastData
{
    /**
     * @param  list<string>  $channels
     * @param  list<string>|null  $clientIds  null/пусто — вся база
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $channels,
        public BroadcastRecurrence $recurrence = BroadcastRecurrence::None,
        public ?Carbon $scheduledAt = null,
        public ?array $clientIds = null,
    ) {}
}
