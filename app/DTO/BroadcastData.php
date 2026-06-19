<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\BroadcastRecurrence;
use Illuminate\Support\Carbon;

/**
 * Данные рассылки из кабинета (Controller → Service). channels — подмножество
 * {telegram, vk, max, email}.
 */
final readonly class BroadcastData
{
    /**
     * @param  list<string>  $channels
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $channels,
        public BroadcastRecurrence $recurrence = BroadcastRecurrence::None,
        public ?Carbon $scheduledAt = null,
    ) {}
}
