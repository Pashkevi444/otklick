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
use App\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;

/**
 * Формирует ответ бота: системный промпт (профиль + опубликованная БЗ) + история
 * диалога → LLM. Распознаёт сентинел эскалации и подставляет вежливый фолбек.
 */
class ReplyComposer
{
    private const int HISTORY_LIMIT = 10;

    public function __construct(
        private readonly LlmClient $llm,
        private readonly PromptBuilder $prompt,
        private readonly KnowledgeEntryRepositoryInterface $knowledge,
        private readonly MessageRepositoryInterface $messages,
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

        if ($answer === '' || $answer === PromptBuilder::ESCALATE) {
            return new BotReply($this->fallback($profile), escalate: true);
        }

        return new BotReply($answer, escalate: false);
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
