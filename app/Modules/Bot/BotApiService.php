<?php

declare(strict_types=1);

namespace App\Modules\Bot;

use App\Modules\Bot\Contracts\BotApi;
use App\Modules\Bot\Services\BotResponder;
use App\Modules\Bot\Services\PromptBuilder;
use App\Modules\Conversations\Models\Conversation;
use App\Shared\DTO\BotReply;
use App\Shared\Models\Tenant;

/**
 * Фасад модуля «Бот»: реализует {@see BotApi}, делегируя внутреннему
 * {@see BotResponder}. Имена методов совпадают с внутренними — потребители
 * меняют только тип в конструкторе.
 */
final class BotApiService implements BotApi
{
    public function __construct(
        private readonly BotResponder $responder,
    ) {}

    public function respond(Tenant $tenant, Conversation $conversation, string $text): BotReply
    {
        return $this->responder->respond($tenant, $conversation, $text);
    }

    public function cancelBookingInCrm(Conversation $conversation): void
    {
        $this->responder->cancelBookingInCrm($conversation);
    }

    public function templateVariables(): array
    {
        return PromptBuilder::VARIABLES;
    }
}
