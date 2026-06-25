<?php

declare(strict_types=1);

namespace App\Modules\Bot\Contracts;

use App\Modules\Bot\BotApiService;
use App\Modules\Conversations\Models\Conversation;
use App\Shared\DTO\BotReply;
use App\Shared\Models\Tenant;

/**
 * Публичный контракт модуля «Бот» — единственная дверь для других модулей.
 * Снаружи доступны только эти методы; BotResponder/ReplyComposer/PromptBuilder —
 * приватная кухня модуля. Реализация — {@see BotApiService}.
 */
interface BotApi
{
    /** Сформировать ответ бота клиенту (база знаний, мастер записи или сценарий). */
    public function respond(Tenant $tenant, Conversation $conversation, string $text): BotReply;

    /** Клиент отменяет запись ([[CANCELLED]]) — отменяем её и в CRM. */
    public function cancelBookingInCrm(Conversation $conversation): void;

    /**
     * Переменные `{{...}}`, доступные в теле промпта, и их описание (для админки СУ).
     *
     * @return array<string, string>
     */
    public function templateVariables(): array;
}
