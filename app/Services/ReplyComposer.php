<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\BotReply;
use App\DTO\BusinessProfile;
use App\Enums\MessageDirection;
use App\Llm\Contracts\LlmClient;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;

/**
 * Формирует ответ бота: системный промпт (профиль + опубликованная БЗ) + история
 * диалога → LLM.
 *
 * Различает два сигнала модели: настоящую эскалацию ([[ESCALATE]] — клиент зовёт
 * человека, жалоба) и непонятку ([[CLARIFY]] — вопрос неясен / нет ответа в базе).
 * На непонятку бот несколько раз переспрашивает (счётчик в диалоге), и только
 * после лимита подряд идущих уточнений диалог уходит на администратора.
 */
class ReplyComposer
{
    private const int HISTORY_LIMIT = 10;

    /** Сколько уточняющих вопросов подряд бот задаёт, прежде чем звать человека. */
    private const int MAX_CLARIFICATIONS = 3;

    public function __construct(
        private readonly LlmClient $llm,
        private readonly PromptBuilder $prompt,
        private readonly KnowledgeEntryRepositoryInterface $knowledge,
        private readonly MessageRepositoryInterface $messages,
        private readonly ConversationRepositoryInterface $conversations,
    ) {}

    public function compose(Tenant $tenant, Conversation $conversation): BotReply
    {
        $profile = BusinessProfile::fromArray($tenant->settings['profile'] ?? []);

        $systemPrompt = $this->prompt->build(
            $tenant->name,
            $profile,
            $this->knowledge->publishedForCurrentTenant(),
        );

        $answer = trim($this->llm->generate($systemPrompt, $this->history($conversation)));

        // Настоящая эскалация (или сбой модели = пустой ответ) — сразу на человека.
        if ($answer === '' || $answer === PromptBuilder::ESCALATE) {
            $this->resetStreak($conversation);

            return new BotReply($this->fallback($profile), escalate: true);
        }

        // Бот не понял / не нашёл ответ — переспрашиваем, пока не упрёмся в лимит.
        if (str_starts_with($answer, PromptBuilder::CLARIFY)) {
            $attempts = $this->conversations->bumpClarificationAttempts($conversation);

            if ($attempts >= self::MAX_CLARIFICATIONS) {
                $this->conversations->resetClarificationAttempts($conversation);

                return new BotReply($this->fallback($profile), escalate: true);
            }

            $question = trim(substr($answer, strlen(PromptBuilder::CLARIFY)));

            return new BotReply($question !== '' ? $question : $this->defaultClarification(), escalate: false);
        }

        // Бот ответил по делу — обнуляем серию непоняток.
        $this->resetStreak($conversation);

        return new BotReply($answer, escalate: false);
    }

    private function resetStreak(Conversation $conversation): void
    {
        if (($conversation->clarification_attempts ?? 0) > 0) {
            $this->conversations->resetClarificationAttempts($conversation);
        }
    }

    private function defaultClarification(): string
    {
        return 'Подскажите, пожалуйста, чуть подробнее, что именно вас интересует?';
    }

    /**
     * @return list<array{role: 'user'|'assistant', content: string}>
     */
    private function history(Conversation $conversation): array
    {
        return $this->messages->recentForConversation($conversation, self::HISTORY_LIMIT)
            ->map(fn (Message $m): array => [
                'role' => $m->direction === MessageDirection::Inbound ? 'user' : 'assistant',
                'content' => (string) $m->text,
            ])
            ->all();
    }

    private function fallback(BusinessProfile $profile): string
    {
        $text = 'Спасибо за обращение! Я передал ваш вопрос администратору — он скоро свяжется с вами.';

        if ($profile->phone !== null && $profile->phone !== '') {
            $text .= " Если вопрос срочный — позвоните: {$profile->phone}.";
        }

        return $text;
    }
}
