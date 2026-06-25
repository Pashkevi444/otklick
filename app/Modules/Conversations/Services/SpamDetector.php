<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Services;

use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Conversations\Repositories\Contracts\MessageRepositoryInterface;
use App\Shared\Enums\MessageDirection;

/**
 * Эвристический детектор спама (без LLM, дёшево и мгновенно). Высокоточный — ловит
 * только явный спам, чтобы не глушить живых клиентов: стоп-слова, telegram-инвайты,
 * пачку ссылок, флуд и повтор одного и того же. Решение — по одному сообщению +
 * недавней истории; не банит контакт навсегда. Что делать со «спамом» решает
 * вызывающий сервис (обычно — промолчать и пометить диалог).
 *
 * Не final/readonly намеренно — мокается в юнит-тестах вызывающих сервисов.
 */
class SpamDetector
{
    /** Флуд: столько входящих за окно — спам. */
    private const int FLOOD_COUNT = 5;

    private const int FLOOD_SECONDS = 12;

    /** Повтор: столько одинаковых входящих подряд — спам. */
    private const int REPEAT_COUNT = 3;

    /**
     * Стоп-слова явного спама. Сопоставляются ТОЛЬКО с начала слова (лево-граница),
     * чтобы «ставк» не ловил «доСТАВКа», а «крипт» — «сКРИПТ». Список специфичен,
     * чтобы не глушить живых клиентов.
     */
    private const array STOP_WORDS = [
        'казино', 'casino', 'казик', '1xbet', 'букмекер', 'ставки на спорт',
        'крипт', 'биткоин', 'bitcoin',
        'инвестиции в', 'пассивный доход', 'лёгкий заработок', 'легкий заработок', 'лёгкие деньги',
        'заработок в интернет', 'подработка на дому', 'удалённая подработка',
        'микрозайм', 'займ онлайн', 'кредит без отказа',
        'порно', 'porn', 'viagra', 'виагра', 'интим услуги', 'для взрослых', '18+',
        'денежный приз', 'вы выиграли', 'розыгрыш приза', 'ищу партнёров для заработка',
    ];

    public function __construct(private readonly MessageRepositoryInterface $messages) {}

    /**
     * Похоже ли сообщение на спам. Текущее входящее уже записано в БД (для флуда/
     * повтора), поэтому учитываем его в истории.
     */
    public function isSpam(Conversation $conversation, string $text): bool
    {
        $normalized = mb_strtolower(trim($text));

        if ($normalized === '') {
            return false;
        }

        return $this->matchesStopWords($normalized)
            || $this->hasTelegramInvite($normalized)
            || $this->tooManyLinks($text)
            || $this->isFloodingOrRepeating($conversation, $normalized);
    }

    private function matchesStopWords(string $text): bool
    {
        foreach (self::STOP_WORDS as $word) {
            // Лево-граница: стоп-слово должно начинать слово (не быть подстрокой
            // внутри другого, как «ставк» в «доставка»).
            if (preg_match('~(?<![\p{L}\p{N}])'.preg_quote($word, '~').'~u', $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /** Инвайт в telegram-канал/чат — типичная приманка спамеров. */
    private function hasTelegramInvite(string $text): bool
    {
        return preg_match('~t\.me/\+|t\.me/joinchat|joinchat/|/\+[a-z0-9_-]{8,}~i', $text) === 1;
    }

    /** Три и более ссылки в одном сообщении — почти всегда рассылка. */
    private function tooManyLinks(string $text): bool
    {
        return preg_match_all('~https?://\S+~i', $text) >= 3;
    }

    private function isFloodingOrRepeating(Conversation $conversation, string $normalized): bool
    {
        $inbound = $this->messages->recentForConversation($conversation, 12)
            ->filter(fn (Message $m): bool => $m->direction === MessageDirection::Inbound)
            ->values();

        // Флуд: слишком много входящих за короткое окно.
        $threshold = now()->subSeconds(self::FLOOD_SECONDS);
        $recentCount = $inbound->filter(fn (Message $m): bool => $m->created_at !== null && $m->created_at->greaterThanOrEqualTo($threshold))->count();
        if ($recentCount >= self::FLOOD_COUNT) {
            return true;
        }

        // Повтор: последние N входящих — один и тот же текст.
        $lastTexts = $inbound->slice(-self::REPEAT_COUNT)
            ->map(fn (Message $m): string => mb_strtolower(trim((string) $m->text)))
            ->values();

        return $lastTexts->count() >= self::REPEAT_COUNT
            && $lastTexts->every(fn (string $t): bool => $t === $normalized);
    }
}
