<?php

declare(strict_types=1);

namespace App\Modules\Flows\Services;

use App\Modules\Booking\Contracts\BookingApi;
use App\Modules\Conversations\Contracts\ConversationsApi;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Flows\Models\Flow;
use App\Modules\Flows\Repositories\Contracts\FlowAbRepositoryInterface;
use App\Modules\Flows\Repositories\Contracts\FlowRepositoryInterface;
use App\Modules\Knowledge\Contracts\KnowledgeApi;
use App\Modules\Knowledge\Models\KnowledgeEntry;
use App\Shared\DTO\BotReply;
use App\Shared\DTO\ReplyKeyboard;
use App\Shared\Models\Tenant;
use App\Shared\Support\FlowExpr;
use App\Shared\Support\KnowledgeLinks;

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
        private ConversationsApi $conversations,
        private BookingApi $booking,
        private FlowAbRepositoryInterface $ab,
        private KnowledgeApi $knowledge,
    ) {}

    public function handle(Tenant $tenant, Conversation $conversation, string $text, bool $strict = false): ?BotReply
    {
        if (is_array($conversation->flow_state) && $conversation->flow_state !== []) {
            return $this->advance($tenant, $conversation, $text);
        }

        // Сценарий запускаем ТОЛЬКО по ключевой фразе-триггеру. $strict (клик по
        // кнопке меню) — точное вхождение фразы. Свободный ввод — то же плюс допуск
        // на опечатки/регистр (Левенштейн), но БЕЗ семантики и морфологии: семантика
        // на эмбеддингах ловила неродственные фразы (напр. «малет стрижка есть
        // примеры?» запускала «как проходит визит»), поэтому убрана.
        $flow = $this->matchLexical($text, $strict);

        return $flow !== null ? $this->start($tenant, $conversation, $flow) : null;
    }

    private function matchLexical(string $text, bool $strict = false): ?Flow
    {
        $needle = str_replace('ё', 'е', mb_strtolower(trim($text)));

        if ($needle === '') {
            return null;
        }

        // Слова сообщения (нормализованные) — для скользящего окна при допуске на опечатки.
        $words = array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', $needle) ?: [],
            static fn (string $w): bool => $w !== '',
        ));

        foreach ($this->flows->activeForCurrentTenant() as $flow) {
            foreach ($flow->triggers as $trigger) {
                if ($this->triggerMatches((string) $trigger, $needle, $words, $strict)) {
                    return $flow;
                }
            }
        }

        return null;
    }

    /**
     * Триггер совпадает, если его фраза целиком встречается в сообщении (регистр и
     * ё→е нормализованы), ИЛИ — в свободном вводе — близка к окну слов сообщения той
     * же длины по расстоянию Левенштейна. Допуск ТОЛЬКО на опечатки/регистр: без
     * морфологии и без семантики (эмбеддинги ловили неродственные фразы). Так
     * «графек работы» поймает триггер «график», а «малет стрижка есть примеры?» не
     * запустит «визит».
     *
     * При $strict (клик по кнопке меню) допуск на опечатки выключен — только точное
     * вхождение фразы (или короткий триггер как отдельное слово).
     *
     * @param  list<string>  $words  нормализованные слова сообщения
     */
    private function triggerMatches(string $trigger, string $needle, array $words, bool $strict = false): bool
    {
        $t = str_replace('ё', 'е', mb_strtolower(trim($trigger)));

        if ($t === '') {
            return false;
        }

        // Точное вхождение фразы-триггера. Короткий триггер (<3) — только как
        // отдельное слово сообщения, иначе «о»/«к» поймают пол-словаря подстрокой.
        if (mb_strlen($t) >= 3) {
            if (mb_strpos($needle, $t) !== false) {
                return true;
            }
        } elseif (in_array($t, $words, true)) {
            return true;
        }

        // Строгий режим (кнопка меню) — без допуска на опечатки.
        if ($strict) {
            return false;
        }

        $triggerWords = array_values(array_filter(
            preg_split('/\s+/u', $t) ?: [],
            static fn (string $w): bool => $w !== '',
        ));
        $n = count($triggerWords);

        if ($n === 0) {
            return false;
        }

        // Очень короткий однословный триггер (<4) не фаззим: одна опечатка ловит
        // слишком много («лак» ↔ «бак»).
        if ($n === 1 && mb_strlen($triggerWords[0]) < 4) {
            return false;
        }

        // Допуск на опечатки: сравниваем фразу-триггер с каждым окном из $n слов
        // сообщения по расстоянию Левенштейна.
        $count = count($words);
        for ($i = 0; $i + $n <= $count; $i++) {
            if ($this->fuzzyEquals(implode(' ', array_slice($words, $i, $n)), $t)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Две строки «равны с точностью до опечаток»: близкая длина и расстояние
     * Левенштейна в пределах ~1 правки на 5 символов.
     */
    private function fuzzyEquals(string $a, string $b): bool
    {
        $la = mb_strlen($a);
        $lb = mb_strlen($b);

        if (abs($la - $lb) > 2) {
            return false;
        }

        $len = max($la, $lb);

        if ($len === 0) {
            return false;
        }

        return $this->levenshtein($a, $b) <= max(1, (int) round($len / 5));
    }

    /**
     * Расстояние Левенштейна по символам (юникод-безопасно: встроенная levenshtein()
     * считает по байтам и врёт на кириллице).
     */
    private function levenshtein(string $a, string $b): int
    {
        $aa = mb_str_split($a);
        $bb = mb_str_split($b);
        $la = count($aa);
        $lb = count($bb);

        if ($la === 0) {
            return $lb;
        }
        if ($lb === 0) {
            return $la;
        }

        $prev = range(0, $lb);

        for ($i = 1; $i <= $la; $i++) {
            $cur = [$i];
            for ($j = 1; $j <= $lb; $j++) {
                $cost = $aa[$i - 1] === $bb[$j - 1] ? 0 : 1;
                $cur[$j] = min($prev[$j] + 1, $cur[$j - 1] + 1, $prev[$j - 1] + $cost);
            }
            $prev = $cur;
        }

        return $prev[$lb];
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

        // Показать элемент базы знаний: бот отдаёт его текст + фото + ссылки и
        // завершает сценарий (кнопка ведёт на такой узел вместо перехода далее).
        if ($action === 'show_knowledge') {
            $this->conversations->setFlowState($conversation, null);

            return $this->knowledgeReply($node, $text);
        }

        $images = $this->nodeImages($node);

        // Узел-вопрос: показываем приглашение и ждём свободный ответ клиента.
        if (($node['type'] ?? 'message') === 'input') {
            $this->conversations->setFlowState($conversation, ['flow_id' => $flow->id, 'node_id' => $nodeId, 'vars' => $vars]);

            return new BotReply($text, escalate: false, images: $images);
        }

        $options = $this->options($node);

        // Узел без кнопок (или явный финал) — завершаем воронку этим сообщением.
        if ($options === [] || $action === 'end') {
            $this->conversations->setFlowState($conversation, null);

            return new BotReply($text, escalate: false, images: $images);
        }

        // Сообщение с кнопками — остаёмся в узле, ждём выбор клиента.
        $this->conversations->setFlowState($conversation, ['flow_id' => $flow->id, 'node_id' => $nodeId, 'vars' => $vars]);

        return new BotReply($text, escalate: false, images: $images, keyboard: ReplyKeyboard::grid(
            array_map(static fn (array $o): string => (string) $o['label'], $options),
            2,
        ));
    }

    /**
     * Ответ узла-действия «показать элемент базы знаний»: текст узла (если задан) +
     * содержимое выбранного элемента + его ссылки и фото. Элемент не выбран/не
     * найден/снят с публикации → отдаём текст узла или мягкий фолбэк.
     *
     * @param  array<string, mixed>  $node
     */
    private function knowledgeReply(array $node, string $introText): BotReply
    {
        $id = trim((string) ($node['knowledge_id'] ?? ''));
        $entry = $id !== '' ? $this->knowledge->find($id) : null;

        if ($entry === null || ! $entry->is_published) {
            return new BotReply(
                $introText !== '' ? $introText : 'Информация временно недоступна, передам ваш вопрос администратору.',
                escalate: $introText === '',
                images: $this->nodeImages($node),
            );
        }

        $body = trim($introText.($introText !== '' ? "\n\n" : '').(string) $entry->content);
        $body = KnowledgeLinks::append($body, $entry->links ?? []);
        $images = array_values(array_unique(array_merge($this->nodeImages($node), $this->entryImages($entry))));

        return new BotReply($body, escalate: false, images: $images);
    }

    /**
     * URL картинок, прикреплённых к узлу сценария (формат БЗ: [{path, url}]).
     *
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function nodeImages(array $node): array
    {
        $urls = [];
        foreach ((array) ($node['images'] ?? []) as $img) {
            $url = is_array($img) ? trim((string) ($img['url'] ?? '')) : '';
            if ($url !== '') {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @return list<string>
     */
    private function entryImages(KnowledgeEntry $entry): array
    {
        $urls = [];
        foreach ($entry->images ?? [] as $img) {
            if ($img['url'] !== '') {
                $urls[] = $img['url'];
            }
        }

        return $urls;
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
