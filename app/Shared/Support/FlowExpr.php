<?php

declare(strict_types=1);

namespace App\Shared\Support;

use App\Modules\Flows\Services\FlowEngine;
use App\Modules\Flows\Services\FlowSimulator;

/**
 * Чистые выражения сценариев-воронок: подстановка переменных и вычисление
 * условий. Единый источник правды для боевого движка ({@see FlowEngine})
 * и тест-прогона в кабинете ({@see FlowSimulator}).
 */
final class FlowExpr
{
    /**
     * Подставляет `{{переменная}}` в текст (неизвестные — пустой строкой).
     *
     * @param  array<string, mixed>  $vars
     */
    public static function interpolate(string $text, array $vars): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/u',
            static fn (array $m): string => (string) ($vars[$m[1]] ?? ''),
            $text,
        );
    }

    /**
     * Вычисляет узел-условие: сравнивает переменную со значением (регистронезависимо).
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $vars
     */
    public static function evalCondition(array $node, array $vars): bool
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
}
