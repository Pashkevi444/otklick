<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\BotReply;
use App\DTO\ReplyKeyboard;
use App\Models\Conversation;
use App\Models\Flow;
use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\FlowRepositoryInterface;

/**
 * Движок сценариев-воронок (no-code логика бота). Воронка — граф узлов
 * (`definition`): узел показывает текст + кнопки, нажатие ведёт к следующему узлу;
 * у узла может быть действие (записать в CRM / позвать человека / завершить).
 *
 * Состояние прохождения хранится в `conversations.flow_state` (как booking_state).
 * Вызывается из {@see BotResponder} ДО LLM: если диалог в воронке — продолжаем; если
 * сообщение совпало с триггером активной воронки — запускаем; иначе null (отвечает
 * ИИ по базе знаний). Свободный текст не по кнопкам выводит из воронки к ИИ —
 * клиент не застревает.
 */
final readonly class FlowEngine
{
    public function __construct(
        private FlowRepositoryInterface $flows,
        private ConversationRepositoryInterface $conversations,
        private BookingFlow $booking,
    ) {}

    public function handle(Tenant $tenant, Conversation $conversation, string $text): ?BotReply
    {
        if (is_array($conversation->flow_state) && $conversation->flow_state !== []) {
            return $this->advance($tenant, $conversation, $text);
        }

        $flow = $this->matchTrigger($text);

        return $flow !== null ? $this->start($tenant, $conversation, $flow) : null;
    }

    private function matchTrigger(string $text): ?Flow
    {
        $needle = mb_strtolower(trim($text));

        if ($needle === '') {
            return null;
        }

        foreach ($this->flows->activeForCurrentTenant() as $flow) {
            foreach ($flow->triggers as $trigger) {
                $t = mb_strtolower(trim((string) $trigger));
                if ($t !== '' && mb_strpos($needle, $t) !== false) {
                    return $flow;
                }
            }
        }

        return null;
    }

    private function start(Tenant $tenant, Conversation $conversation, Flow $flow): ?BotReply
    {
        $startId = (string) ($flow->definition['start'] ?? '');
        $node = $this->node($flow, $startId);

        if ($node === null) {
            return null;
        }

        return $this->enter($tenant, $conversation, $flow, $startId, $node);
    }

    private function advance(Tenant $tenant, Conversation $conversation, string $text): ?BotReply
    {
        $state = $conversation->flow_state ?? [];
        $flow = $this->flows->find((string) ($state['flow_id'] ?? ''));

        if ($flow === null || ! $flow->is_active) {
            $this->conversations->setFlowState($conversation, null);

            return null;
        }

        $node = $this->node($flow, (string) ($state['node_id'] ?? ''));
        $next = $node !== null ? $this->matchOption($node, $text) : null;

        // Клиент написал не по кнопкам (или узел пропал) — выходим из воронки к ИИ.
        if ($next === null) {
            $this->conversations->setFlowState($conversation, null);

            return null;
        }

        $nextNode = $this->node($flow, $next);

        if ($nextNode === null) {
            $this->conversations->setFlowState($conversation, null);

            return null;
        }

        return $this->enter($tenant, $conversation, $flow, $next, $nextNode);
    }

    /**
     * Входит в узел: выполняет действие (запись/эскалация/финал) или показывает
     * сообщение с кнопками и остаётся ждать выбор.
     *
     * @param  array<string, mixed>  $node
     */
    private function enter(Tenant $tenant, Conversation $conversation, Flow $flow, string $nodeId, array $node): BotReply
    {
        $action = (string) ($node['action'] ?? 'none');
        $text = trim((string) ($node['text'] ?? ''));
        $options = $this->options($node);

        if ($action === 'start_booking') {
            $this->conversations->setFlowState($conversation, null);

            return $this->booking->start($tenant, $conversation)
                ?? new BotReply($text !== '' ? $text : 'Передаю администратору.', escalate: true);
        }

        if ($action === 'escalate') {
            $this->conversations->setFlowState($conversation, null);

            return new BotReply($text !== '' ? $text : 'Передаю вопрос администратору.', escalate: true);
        }

        // Узел без кнопок (или явный финал) — завершаем воронку этим сообщением.
        if ($options === [] || $action === 'end') {
            $this->conversations->setFlowState($conversation, null);

            return new BotReply($text, escalate: false);
        }

        // Сообщение с кнопками — остаёмся в узле, ждём выбор клиента.
        $this->conversations->setFlowState($conversation, ['flow_id' => $flow->id, 'node_id' => $nodeId]);

        return new BotReply($text, escalate: false, keyboard: ReplyKeyboard::grid(
            array_map(static fn (array $o): string => (string) $o['label'], $options),
            2,
        ));
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function matchOption(array $node, string $text): ?string
    {
        $choice = mb_strtolower(trim($text));

        foreach ($this->options($node) as $option) {
            if (mb_strtolower(trim((string) $option['label'])) === $choice) {
                return (string) $option['next'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<array{label: string, next: string}>
     */
    private function options(array $node): array
    {
        $out = [];

        foreach ((array) ($node['options'] ?? []) as $option) {
            if (is_array($option) && ($option['label'] ?? null) !== null && ($option['next'] ?? null) !== null) {
                $out[] = ['label' => (string) $option['label'], 'next' => (string) $option['next']];
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function node(Flow $flow, string $id): ?array
    {
        $node = $flow->definition['nodes'][$id] ?? null;

        return is_array($node) ? $node : null;
    }
}
