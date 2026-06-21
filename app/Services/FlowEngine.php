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
use App\Repositories\Contracts\FlowAbRepositoryInterface;
use App\Repositories\Contracts\FlowRepositoryInterface;
use App\Support\FlowExpr;
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
    /**
     * Служебные слова (предлоги/союзы/местоимения/частицы/вопросительные). Ключами
     * триггера они бесполезны и ловят пол-словаря: предлог «к» как подстрока сидит
     * в «стри[ж]ки», и триггер «как к вам попасть» матчил «удлиненные стрижки».
     * Совпадение ведём только по СОДЕРЖАТЕЛЬНЫМ словам фразы-триггера.
     */
    private const array STOPWORDS = [
        'в', 'во', 'на', 'к', 'ко', 'с', 'со', 'по', 'о', 'об', 'обо', 'от', 'до', 'за', 'из', 'у', 'под', 'над', 'при', 'про', 'без', 'для', 'через',
        'и', 'а', 'но', 'или', 'да', 'что', 'чтобы', 'как', 'если', 'когда', 'где', 'куда', 'откуда', 'чем', 'то', 'же', 'ли', 'бы', 'не', 'ни', 'вот', 'уже', 'еще', 'там', 'тут',
        'я', 'ты', 'он', 'она', 'оно', 'мы', 'вы', 'они', 'вас', 'вам', 'нас', 'нам', 'мне', 'меня', 'тебя', 'тебе', 'его', 'ее', 'их', 'им', 'мой', 'ваш', 'наш', 'это', 'эта', 'этот', 'тот', 'свой', 'весь',
    ];

    public function __construct(
        private FlowRepositoryInterface $flows,
        private ConversationRepositoryInterface $conversations,
        private BookingFlow $booking,
        private Embedder $embedder,
        private FlowAbRepositoryInterface $ab,
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

        // Слова сообщения (для матча коротких слов триггера целым словом) и их
        // основы (морфология: триггер «акция» ловит «акции/акцию/акций»).
        $words = array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', $needle) ?: [],
            static fn (string $w): bool => $w !== '',
        ));
        $stems = array_values(array_filter(array_map(RussianStem::stem(...), $words), static fn (string $s): bool => $s !== ''));

        foreach ($this->flows->activeForCurrentTenant() as $flow) {
            foreach ($flow->triggers as $trigger) {
                if ($this->triggerMatches((string) $trigger, $needle, $words, $stems)) {
                    return $flow;
                }
            }
        }

        return null;
    }

    /**
     * Триггер совпадает, если он целиком — подстрока сообщения, ИЛИ совпала основа
     * ХОТЯ БЫ ОДНОГО СОДЕРЖАТЕЛЬНОГО слова триггера с основой слова сообщения
     * (морфология: «акция» ≈ «акции»). Многословный триггер — это набор ключевых
     * слов, а не дословная фраза: «акции скидка» ловит «…какие нибудь акции есть…».
     * Служебные слова ({@see self::STOPWORDS}) и совсем короткие слова из ключей
     * исключаем — иначе предлог «к» матчит «стри[ж]ки».
     *
     * @param  list<string>  $words  слова сообщения (для матча коротких слов целиком)
     * @param  list<string>  $stems  основы слов сообщения
     */
    private function triggerMatches(string $trigger, string $needle, array $words, array $stems): bool
    {
        $t = str_replace('ё', 'е', mb_strtolower(trim($trigger)));

        if ($t === '') {
            return false;
        }

        // Точный матч: фраза-триггер целиком встречается в сообщении (для коротких
        // фраз — только как отдельное слово, иначе «к» снова поймал бы «стрижки»).
        if (mb_strlen($t) >= 4 ? mb_strpos($needle, $t) !== false : in_array($t, $words, true)) {
            return true;
        }

        // По каждому СОДЕРЖАТЕЛЬНОМУ слову триггера (запятая/пробел = ключевые слова).
        foreach (preg_split('/\s+/u', $t) ?: [] as $word) {
            if ($word === '' || in_array($word, self::STOPWORDS, true)) {
                continue;
            }

            // Короткое слово (нет надёжной основы) — матчим ТОЛЬКО как целое слово
            // сообщения, не подстрокой («лак» не должен ловить «потолок»).
            if (mb_strlen($word) < 4) {
                if (in_array($word, $words, true)) {
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
        // Контекст подстановки: встроенные переменные клиента + захваченные в воронке.
        // Хранится в flow_state только захваченное; встроенные берём свежими каждый шаг.
        $ctx = array_merge($this->builtinVars($conversation), $vars);

        // Узел-условие проходим сразу к ветке; глубина — защита от циклов условий.
        if (($node['type'] ?? 'message') === 'condition') {
            if ($depth > 20) {
                $this->conversations->setFlowState($conversation, null);

                return null;
            }
            $branch = (string) ($node[FlowExpr::evalCondition($node, $ctx) ? 'next' : 'else'] ?? '');
            $target = $this->node($flow, $branch);
            if ($target === null) {
                $this->conversations->setFlowState($conversation, null);

                return null;
            }

            return $this->enter($tenant, $conversation, $flow, $branch, $target, $vars, $depth + 1);
        }

        // A/B-сплит: делим трафик между вариантами (липко на диалог), идём в выбранный.
        if (($node['type'] ?? 'message') === 'split') {
            $variants = $this->variants($node);
            if ($depth > 20 || $variants === []) {
                $this->conversations->setFlowState($conversation, null);

                return null;
            }
            $chosen = $this->pickVariant($flow, $conversation, $variants);
            $target = $this->node($flow, $chosen['next']);
            if ($target === null) {
                $this->conversations->setFlowState($conversation, null);

                return null;
            }

            return $this->enter($tenant, $conversation, $flow, $chosen['next'], $target, $vars, $depth + 1);
        }

        $action = (string) ($node['action'] ?? 'none');
        $text = FlowExpr::interpolate(trim((string) ($node['text'] ?? '')), $ctx);

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
     * Варианты A/B-сплита (метка → переход). Пустая метка → авто «Вариант N».
     *
     * @param  array<string, mixed>  $node
     * @return list<array{label: string, next: string}>
     */
    private function variants(array $node): array
    {
        $out = [];
        foreach (array_values((array) ($node['variants'] ?? [])) as $i => $v) {
            if (is_array($v) && ($v['next'] ?? null) !== null && (string) $v['next'] !== '') {
                $label = trim((string) ($v['label'] ?? ''));
                $out[] = ['label' => $label !== '' ? $label : 'Вариант '.($i + 1), 'next' => (string) $v['next']];
            }
        }

        return $out;
    }

    /**
     * Возвращает вариант для диалога: уже назначенный (липко) или случайный новый
     * (и записывает назначение для статистики конверсии).
     *
     * @param  list<array{label: string, next: string}>  $variants
     * @return array{label: string, next: string}
     */
    private function pickVariant(Flow $flow, Conversation $conversation, array $variants): array
    {
        $existing = $this->ab->variantFor($flow->id, (string) $conversation->id);
        if ($existing !== null) {
            foreach ($variants as $variant) {
                if ($variant['label'] === $existing) {
                    return $variant;
                }
            }
        }

        $chosen = $variants[random_int(0, count($variants) - 1)];
        $this->ab->assign($flow->id, (string) $conversation->id, $chosen['label']);

        return $chosen;
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

    /**
     * Встроенные переменные из карточки клиента — доступны в текстах сценария как
     * `{{client_name}}` / `{{client_phone}}` / `{{client_email}}` без переспроса.
     *
     * @return array<string, string>
     */
    private function builtinVars(Conversation $conversation): array
    {
        return [
            'client_name' => (string) $conversation->displayName(),
            'client_phone' => (string) $conversation->displayPhone(),
            'client_email' => (string) $conversation->displayEmail(),
        ];
    }
}
