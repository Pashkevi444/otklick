<?php

declare(strict_types=1);

namespace App\Modules\Flows\Services;

use App\Modules\Knowledge\Models\KnowledgeEntry;
use App\Modules\Knowledge\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Shared\Support\FlowExpr;
use App\Shared\Support\KnowledgeLinks;

/**
 * Сухой прогон сценария-воронки для теста в кабинете: шагает по графу как боевой
 * {@see FlowEngine} (узлы вопрос/условие/A-B/сообщение, переменные, подстановка),
 * но БЕЗ побочных эффектов — ничего не пишет в БД, запись/эскалацию лишь обозначает.
 * Состояние (узел + переменные) возвращается клиенту и приходит обратно следующим шагом.
 */
final class FlowSimulator
{
    public function __construct(
        private readonly KnowledgeEntryRepositoryInterface $knowledge,
    ) {}

    /**
     * @param  array<string, mixed>  $definition  {start, nodes}
     * @param  array{node: ?string, vars?: array<string,mixed>}|null  $state  null — начать с старта
     * @return array{reply: ?string, buttons: list<string>, vars: array<string,mixed>, node: ?string, done: bool, note: ?string, images: list<string>}
     */
    public function step(array $definition, ?array $state, ?string $text): array
    {
        $nodes = (array) ($definition['nodes'] ?? []);
        $vars = (array) ($state['vars'] ?? []);

        if ($state === null) {
            return $this->enter($nodes, (string) ($definition['start'] ?? ''), $vars, 0);
        }

        $node = $nodes[(string) ($state['node'] ?? '')] ?? null;
        if (! is_array($node)) {
            return $this->stop($vars, 'Узел не найден — воронка прервана.');
        }

        if (($node['type'] ?? 'message') === 'input') {
            $variable = trim((string) ($node['variable'] ?? ''));
            if ($variable !== '') {
                $vars[$variable] = trim((string) $text);
            }

            return $this->enter($nodes, (string) ($node['next'] ?? ''), $vars, 0);
        }

        // Сообщение с кнопками: ищем кнопку по тексту; не совпало → выход к ИИ.
        $next = $this->matchButton($node, (string) $text);
        if ($next === null) {
            return $this->stop($vars, 'Свободный текст не по кнопкам — в боте дальше ответит ИИ.');
        }

        return $this->enter($nodes, $next, $vars, 0);
    }

    /**
     * @param  array<string, mixed>  $nodes
     * @param  array<string, mixed>  $vars
     * @return array{reply: ?string, buttons: list<string>, vars: array<string,mixed>, node: ?string, done: bool, note: ?string, images: list<string>}
     */
    private function enter(array $nodes, string $nodeId, array $vars, int $depth): array
    {
        $node = $nodes[$nodeId] ?? null;
        if (! is_array($node) || $depth > 20) {
            return $this->stop($vars, 'Тупик: переход в никуда (проверьте связи узлов).');
        }

        // Встроенные переменные клиента в тесте — образцовые (в боте берутся из карточки).
        $ctx = array_merge(['client_name' => 'Анна', 'client_phone' => '+7 999 123-45-67', 'client_email' => 'anna@mail.ru'], $vars);
        $type = (string) ($node['type'] ?? 'message');

        if ($type === 'condition') {
            $branch = (string) ($node[FlowExpr::evalCondition($node, $ctx) ? 'next' : 'else'] ?? '');

            return $this->enter($nodes, $branch, $vars, $depth + 1);
        }

        if ($type === 'split') {
            $variants = $this->variants($node);
            if ($variants === []) {
                return $this->stop($vars, 'A/B без вариантов.');
            }
            $chosen = $variants[random_int(0, count($variants) - 1)];
            $result = $this->enter($nodes, $chosen['next'], $vars, $depth + 1);
            $result['note'] = 'A/B: вариант '.$chosen['label'].($result['note'] !== null ? ' · '.$result['note'] : '');

            return $result;
        }

        $action = (string) ($node['action'] ?? 'none');
        $textOut = FlowExpr::interpolate(trim((string) ($node['text'] ?? '')), $ctx);
        $images = $this->nodeImages($node);

        if ($action === 'start_booking') {
            return $this->stop($vars, 'Действие: начнётся запись в YClients.', $textOut !== '' ? $textOut : null);
        }
        if ($action === 'escalate') {
            return $this->stop($vars, 'Действие: передать администратору.', $textOut !== '' ? $textOut : null);
        }
        if ($action === 'show_knowledge') {
            return $this->knowledgeStep($node, $textOut, $images, $vars);
        }

        if ($type === 'input') {
            return ['reply' => $textOut, 'buttons' => [], 'vars' => $vars, 'node' => $nodeId, 'done' => false, 'note' => 'Жду ответ → переменная «'.($node['variable'] ?? '?').'»', 'images' => $images];
        }

        $buttons = array_map(static fn (array $o): string => (string) $o['label'], $this->options($node));

        if ($buttons === [] || $action === 'end') {
            return ['reply' => $textOut, 'buttons' => [], 'vars' => $vars, 'node' => null, 'done' => true, 'note' => null, 'images' => $images];
        }

        return ['reply' => $textOut, 'buttons' => $buttons, 'vars' => $vars, 'node' => $nodeId, 'done' => false, 'note' => null, 'images' => $images];
    }

    /**
     * Шаг действия «показать элемент базы знаний» в тест-прогоне: подставляем
     * содержимое выбранного элемента + ссылки + фото и завершаем сценарий.
     *
     * @param  array<string, mixed>  $node
     * @param  list<string>  $nodeImages
     * @param  array<string, mixed>  $vars
     * @return array{reply: ?string, buttons: list<string>, vars: array<string,mixed>, node: ?string, done: bool, note: ?string, images: list<string>}
     */
    private function knowledgeStep(array $node, string $introText, array $nodeImages, array $vars): array
    {
        $id = trim((string) ($node['knowledge_id'] ?? ''));
        $entry = $id !== '' ? $this->knowledge->find($id) : null;

        if ($entry === null) {
            return $this->stop($vars, 'Действие: показать элемент базы знаний (элемент не выбран).', $introText !== '' ? $introText : null);
        }

        $body = trim($introText.($introText !== '' ? "\n\n" : '').(string) $entry->content);
        $body = KnowledgeLinks::append($body, $entry->links ?? []);
        $images = array_values(array_unique(array_merge($nodeImages, $this->entryImages($entry))));

        return ['reply' => $body, 'buttons' => [], 'vars' => $vars, 'node' => null, 'done' => true, 'note' => 'Действие: показан элемент БЗ «'.$entry->title.'».', 'images' => $images];
    }

    /**
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
     * @param  array<string, mixed>  $vars
     * @return array{reply: ?string, buttons: list<string>, vars: array<string,mixed>, node: ?string, done: bool, note: ?string, images: list<string>}
     */
    private function stop(array $vars, string $note, ?string $reply = null): array
    {
        return ['reply' => $reply, 'buttons' => [], 'vars' => $vars, 'node' => null, 'done' => true, 'note' => $note, 'images' => []];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function matchButton(array $node, string $text): ?string
    {
        $choice = mb_strtolower(trim($text));
        foreach ($this->options($node) as $option) {
            if (mb_strtolower(trim($option['label'])) === $choice) {
                return $option['next'];
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
        foreach ((array) ($node['options'] ?? []) as $o) {
            if (is_array($o) && ($o['label'] ?? null) !== null && ($o['next'] ?? null) !== null) {
                $out[] = ['label' => (string) $o['label'], 'next' => (string) $o['next']];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<array{label: string, next: string}>
     */
    private function variants(array $node): array
    {
        $out = [];
        foreach (array_values((array) ($node['variants'] ?? [])) as $i => $v) {
            if (is_array($v) && (string) ($v['next'] ?? '') !== '') {
                $label = trim((string) ($v['label'] ?? ''));
                $out[] = ['label' => $label !== '' ? $label : 'Вариант '.($i + 1), 'next' => (string) $v['next']];
            }
        }

        return $out;
    }
}
