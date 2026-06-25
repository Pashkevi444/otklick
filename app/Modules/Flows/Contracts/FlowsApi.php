<?php

declare(strict_types=1);

namespace App\Modules\Flows\Contracts;

use App\Modules\Conversations\Models\Conversation;
use App\Modules\Flows\FlowsApiService;
use App\Shared\DTO\BotReply;
use App\Shared\Models\Tenant;

/**
 * Публичный контракт модуля «Сценарии» — единственная дверь для других модулей.
 * Снаружи доступен только этот метод; FlowEngine/FlowService/FlowSimulator —
 * приватная кухня модуля. Реализация — {@see FlowsApiService}.
 */
interface FlowsApi
{
    /**
     * Сценарии-воронки (no-code логика владельца): продолжает активную воронку
     * диалога или запускает новую по триггеру — вызывается ДО LLM. Не сработало →
     * null (отвечает ИИ по базе знаний). $strict (клик по кнопке меню) — точное
     * совпадение триггера без допуска на опечатки.
     */
    public function handle(Tenant $tenant, Conversation $conversation, string $text, bool $strict = false): ?BotReply;
}
