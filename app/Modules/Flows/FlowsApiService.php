<?php

declare(strict_types=1);

namespace App\Modules\Flows;

use App\Modules\Conversations\Models\Conversation;
use App\Modules\Flows\Contracts\FlowsApi;
use App\Modules\Flows\Services\FlowEngine;
use App\Shared\DTO\BotReply;
use App\Shared\Models\Tenant;

/**
 * Фасад модуля «Сценарии»: реализует {@see FlowsApi}, делегируя внутреннему
 * движку {@see FlowEngine}. Имена методов совпадают с внутренними — потребители
 * меняют только тип в конструкторе.
 */
final class FlowsApiService implements FlowsApi
{
    public function __construct(
        private readonly FlowEngine $engine,
    ) {}

    public function handle(Tenant $tenant, Conversation $conversation, string $text, bool $strict = false): ?BotReply
    {
        return $this->engine->handle($tenant, $conversation, $text, $strict);
    }
}
