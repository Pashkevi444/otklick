<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\ConversationActivity;
use App\Models\Message;
use App\Tenancy\TestContext;

/**
 * На создание сообщения транслирует {@see ConversationActivity} — чтобы открытый
 * в кабинете диалог и веб-виджет подтянули его живьём (без поллинга). Тестовую
 * песочницу ({@see TestContext}) не транслируем — её прогон не виден реальным
 * операторам. Сообщения создаются ТОЛЬКО через репозиторий (рассылки их не пишут),
 * поэтому обсервер ловит ровно поток живого чата.
 *
 * Событие транслируется СИНХРОННО (ShouldBroadcastNow) — это сетевой вызов к Reverb.
 * Оборачиваем в rescue(): недоступный/тормозящий Reverb НЕ должен ронять запись
 * сообщения и доставку ответа оператора (фолбэк — поллинг подтянет сообщение).
 */
final readonly class MessageObserver
{
    public function __construct(private TestContext $test) {}

    public function created(Message $message): void
    {
        if ($this->test->active()) {
            return;
        }

        $conversation = $message->conversation()->with('channel')->first();
        if ($conversation !== null) {
            rescue(fn () => ConversationActivity::dispatchFor($conversation), report: true);
        }
    }
}
