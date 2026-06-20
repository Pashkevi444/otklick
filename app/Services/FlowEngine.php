<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\BotReply;
use App\DTO\ReplyKeyboard;
use App\Llm\Contracts\Embedder;
use App\Models\Conversation;
use App\Models\Flow;
use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\FlowRepositoryInterface;
use App\Support\RussianStem;
use App\Support\Vectors;
use Throwable;

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
        private Embedder $embedder,
    ) {}

    public function handle(Tenant $tenant, Conversation $conversation, string $text): ?BotReply
    {
        if (is_array($conversation->flow_state) && $conversation->flow_state !== []) {
            return $this->advance($tenant, $conversation, $text);
        }

        // Сначала быстрый матчинг по основе слова, затем (если не совпало) —
        // семантический по эмбеддингам (ловит синонимы: «скидка» ≈ «акция»).
        $flow = $this->matchLexical($text) ?? $this->matchSemantic($text);

        return $flow !== null ? $this->start($tenant, $conversation, $flow) : null;
    }

    private function matchLexical(string $text): ?Flow
    {
        $needle = str_replace('ё', 'е', mb_strtolower(trim($text)));

        if ($needle === '') {
            return null;
        }

        // Основы слов сообщения — чтобы триггер «акция» поймал «акции/акцию/акций».
        $stems = array_values(array_filter(array_map(
            RussianStem::stem(...),
            preg_split('/[^\p{L}\p{N}]+/u', $needle) ?: [],
        ), static fn (string $s): bool => $s !== ''));

        foreach ($this->flows->activeForCurrentTenant() as $flow) {
            foreach ($flow->triggers as $trigger) {
                if ($this->triggerMatches((string) $trigger, $needle, $stems)) {
                    return $flow;
                }
            }
        }

        return null;
    }

    /**
     * Триггер совпадает, если он целиком — подстрока сообщения, ИЛИ совпала основа
     * ХОТЯ БЫ ОДНОГО слова триггера с основой слова сообщения (морфология: «акция»
     * ≈ «акции»). Многословный триггер — это набор ключевых слов, а не дословная
     * фраза: «акции скидка» ловит «…какие нибудь акции есть…».
     *
     * @param  list<string>  $stems
     */
    private function triggerMatches(string $trigger, string $needle, array $stems): bool
    {
        $t = str_replace('ё', 'е', mb_strtolower(trim($trigger)));

        if ($t === '') {
            return false;
        }

        // Точный матч: триггер целиком встречается в сообщении.
        if (mb_strpos($needle, $t) !== false) {
            return true;
        }

        // По каждому слову триггера (через запятую/пробел = ключевые слова).
        foreach (preg_split('/\s+/u', $t) ?: [] as $word) {
            if ($word === '') {
                continue;
            }
            if (mb_strlen($word) < 4) {
                if (mb_strpos($needle, $word) !== false) {
                    return true;
                }

                continue;
            }

            $wStem = RussianStem::stem($word);
            foreach ($stems as $stem) {
                $min = min(mb_strlen($wStem), mb_strlen($stem));
                if ($min >= 3 && (str_starts_with($stem, $wStem) || str_starts_with($wStem, $stem))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Семантический матчинг: эмбеддим сообщение ОДИН раз и сравниваем с заранее
     * посчитанными векторами фраз-триггеров (косинус). Запускаем сценарий с
     * максимальной близостью выше порога. Эмбеддим только если есть активные
     * сценарии с векторами — иначе ни одного вызова эмбеддера (экономим).
     */
    private function matchSemantic(string $text): ?Flow
    {
        if (trim($text) === '') {
            return null;
        }

        $candidates = $this->flows->activeForCurrentTenant()
            ->filter(static fn (Flow $f): bool => is_array($f->trigger_embeddings) && $f->trigger_embeddings !== []);

        if ($candidates->isEmpty()) {
            return null;
        }

        try {
            $vector = $this->embedder->embed($text);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $threshold = (float) config('services.flows.semantic_threshold', 0.6);
        $best = null;
        $bestScore = -1.0;

        foreach ($candidates as $flow) {
            foreach ($flow->trigger_embeddings ?? [] as $triggerVector) {
                $score = Vectors::cosine($vector, $triggerVector);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $flow;
                }
            }
        }

        return $bestScore >= $threshold ? $best : null;
    }

    private function start(Tenant $tenant, Conversation $conversation, Flow $flow): ?BotReply
    {
        $startId = (string) ($flow->definition['start'] ?? '');
        $node = $this->node($flow, $startId);

        if ($node === null) {
            return null;
        }

        return $this->enter($tenant, $conversation, $flow, $startId, $node, []);
    }

    private function advance(Tenant $tenant, Conversation $conversation, string $text): ?BotReply
    {
        $state = $conversation->flow_state ?? [];
        $flow = $this->flows->find((string) ($state['flow_id'] ?? ''));

        if ($flow === null || ! $flow->is_active) {
            $this->conversations->setFlowState($conversation, null);

            return null;
        }

        $vars = (array) ($state['vars'] ?? []);
        $node = $this->node($flow, (string) ($state['node_id'] ?? ''));

        if ($node === null) {
            $this->conversations->setFlowState($conversation, null);

            return null;
        }

        // Узел-вопрос: ответ клиента (свободный текст) сохраняем в переменную и идём дальше.
        if (($node['type'] ?? 'message') === 'input') {
            $variable = trim((string) ($node['variable'] ?? ''));
            if ($variable !== '') {
                $vars[$variable] = trim($text);
            }
            $next = (string) ($node['next'] ?? '');
        } else {
            // Сообщение с кнопками: выбор клиента; не по кнопкам → выходим к ИИ.
            $next = $this->matchOption($node, $text);
        }

        $nextNode = $next !== null ? $this->node($flow, $next) : null;

        if ($nextNode === null) {
            $this->conversations->setFlowState($conversation, null);

            return null;
        }

        return $this->enter($tenant, $conversation, $flow, $next, $nextNode, $vars);
    }

    /**
     * Входит в узел: узел-условие авто-ветвится по переменной (без участия
     * клиента), узел-вопрос ждёт свободный ответ, действие выполняет запись/
     * эскалацию/финал, обычное сообщение показывает кнопки. `{{переменные}}`
     * подставляются в текст.
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $vars
     */
    private function enter(Tenant $tenant, Conversation $conversation, Flow $flow, string $nodeId, array $node, array $vars, int $depth = 0): ?BotReply
    {
        // Узел-условие проходим сразу к ветке; глубина — защита от циклов условий.
        if (($node['type'] ?? 'message') === 'condition') {
            if ($depth > 20) {
                $this->conversations->setFlowState($conversation, null);

                return null;
            }
            $branch = (string) ($node[$this->evalCondition($node, $vars) ? 'next' : 'else'] ?? '');
            $target = $this->node($flow, $branch);
            if ($target === null) {
                $this->conversations->setFlowState($conversation, null);

                return null;
            }

            return $this->enter($tenant, $conversation, $flow, $branch, $target, $vars, $depth + 1);
        }

        $action = (string) ($node['action'] ?? 'none');
        $text = $this->interpolate(trim((string) ($node['text'] ?? '')), $vars);

        if ($action === 'start_booking') {
            $this->conversations->setFlowState($conversation, null);

            return $this->booking->start($tenant, $conversation)
                ?? new BotReply($text !== '' ? $text : 'Передаю администратору.', escalate: true);
        }

        if ($action === 'escalate') {
            $this->conversations->setFlowState($conversation, null);

            return new BotReply($text !== '' ? $text : 'Передаю вопрос администратору.', escalate: true);
        }

        // Узел-вопрос: показываем приглашение и ждём свободный ответ клиента.
        if (($node['type'] ?? 'message') === 'input') {
            $this->conversations->setFlowState($conversation, ['flow_id' => $flow->id, 'node_id' => $nodeId, 'vars' => $vars]);

            return new BotReply($text, escalate: false);
        }

        $options = $this->options($node);

        // Узел без кнопок (или явный финал) — завершаем воронку этим сообщением.
        if ($options === [] || $action === 'end') {
            $this->conversations->setFlowState($conversation, null);

            return new BotReply($text, escalate: false);
        }

        // Сообщение с кнопками — остаёмся в узле, ждём выбор клиента.
        $this->conversations->setFlowState($conversation, ['flow_id' => $flow->id, 'node_id' => $nodeId, 'vars' => $vars]);

        return new BotReply($text, escalate: false, keyboard: ReplyKeyboard::grid(
            array_map(static fn (array $o): string => (string) $o['label'], $options),
            2,
        ));
    }

    /**
     * Вычисляет узел-условие: сравнивает переменную с значением (регистронезависимо).
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $vars
     */
    private function evalCondition(array $node, array $vars): bool
    {
        $actual = mb_strtolower(trim((string) ($vars[(string) ($node['variable'] ?? '')] ?? '')));
        $expected = mb_strtolower(trim((string) ($node['value'] ?? '')));

        return match ((string) ($node['operator'] ?? 'eq')) {
            'neq' => $actual !== $expected,
            'contains' => $expected !== '' && mb_strpos($actual, $expected) !== false,
            'filled' => $actual !== '',
            default => $actual === $expected, // eq
        };
    }

    /**
     * Подставляет `{{переменная}}` в текст (неизвестные — пустой строкой).
     *
     * @param  array<string, mixed>  $vars
     */
    private function interpolate(string $text, array $vars): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/u',
            static fn (array $m): string => (string) ($vars[$m[1]] ?? ''),
            $text,
        );
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
