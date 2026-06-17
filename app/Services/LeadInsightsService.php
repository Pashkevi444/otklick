<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\Analytics\AnalyticsRange;
use App\DTO\Analytics\Gap;
use App\DTO\Analytics\LeadAnalytics;
use App\DTO\Analytics\MetricCard;
use App\Llm\Contracts\LlmClient;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Throwable;

/**
 * ИИ-разбор «чего и где не хватает» по лидам: метрики периода → LLM → список
 * наблюдений с рекомендациями. Результат кэшируется по тенанту+периоду (НЕ
 * считается при каждой загрузке страницы): обновляется по устареванию (через
 * фоновую задачу) или по кнопке. Если LLM недоступна/вернула не JSON — отдаём
 * детерминированный разбор по правилам (фолбек), чтобы блок никогда не пустовал.
 */
final readonly class LeadInsightsService
{
    private const int TTL = 60 * 60 * 48;          // храним 2 суток

    private const int STALE_AFTER = 60 * 60 * 12;  // считаем устаревшим через 12 ч

    private const string SYSTEM_PROMPT =
        'Ты — аналитик-консультант по привлечению клиентов для локального бизнеса. '.
        'На основе метрик за период определи 3–5 главных проблем «чего и где не хватает» в работе с лидами '.
        'и дай по каждой конкретную рекомендацию. Пиши по-русски, кратко и по делу. '.
        'Анализируй ТОЛЬКО данные и действия бизнеса (как он работает с лидами, базой знаний, записью). '.
        'НЕ критикуй и не оценивай сам сервис «Отклик», его ИИ-бота или платформу — считай инструмент исправным; '.
        'рекомендации давай бизнесу о том, что улучшить в его данных и процессах. '.
        'Верни СТРОГО JSON-массив объектов вида '.
        '{"severity":"high|medium|low","title":"кратко","detail":"что не так и насколько","action":"что сделать"}. '.
        'Без markdown и любого текста вне JSON.';

    public function __construct(
        private LeadAnalyticsService $analytics,
        private LlmClient $llm,
        private TenantContext $tenant,
        private CacheRepository $cache,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function cached(AnalyticsRange $range): ?array
    {
        $tid = $this->tenant->id();

        return $tid !== null ? $this->cache->get($this->key($tid, $range)) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function isStale(array $payload): bool
    {
        return (int) ($payload['ts'] ?? 0) < now()->timestamp - self::STALE_AFTER;
    }

    /**
     * Пересчитывает разбор (вызов LLM) и кладёт в кэш.
     *
     * @return array<string, mixed>|null
     */
    public function refresh(AnalyticsRange $range): ?array
    {
        $tid = $this->tenant->id();

        if ($tid === null) {
            return null;
        }

        $generated = $this->generate($this->analytics->forPeriod($range));

        $payload = [
            'items' => $generated['items'],
            'source' => $generated['source'],
            'ts' => now()->timestamp,
            'generatedAt' => now()->format('d.m.Y H:i'),
            'period' => $range->key,
        ];

        $this->cache->put($this->key($tid, $range), $payload, self::TTL);

        return $payload;
    }

    /**
     * @return array{items: list<array<string, mixed>>, source: string}
     */
    private function generate(LeadAnalytics $analytics): array
    {
        try {
            $raw = $this->llm->generate(self::SYSTEM_PROMPT, [
                ['role' => 'user', 'content' => $this->summary($analytics)],
            ]);

            $items = $this->parse($raw);

            if ($items !== []) {
                return ['items' => $items, 'source' => 'ai'];
            }
        } catch (Throwable $e) {
            report($e);
        }

        // Фолбек: детерминированный разбор по правилам.
        return [
            'items' => array_map(fn (Gap $g): array => $g->toArray(), $analytics->gaps),
            'source' => 'rules',
        ];
    }

    private function summary(LeadAnalytics $analytics): string
    {
        $lines = ['Период: '.$analytics->period['label']];

        foreach ($analytics->kpis as $kpi) {
            /** @var MetricCard $kpi */
            $lines[] = "- {$kpi->label}: {$kpi->value}{$kpi->unit}";
        }

        $channels = array_map(fn ($s): string => "{$s->label} {$s->value}", $analytics->byChannel);
        $lines[] = 'Каналы: '.($channels !== [] ? implode(', ', $channels) : 'нет');

        // Автосигналы по правилам — как подсказки модели.
        $signals = array_map(fn (Gap $g): string => $g->title, $analytics->gaps);
        $lines[] = 'Замеченные сигналы: '.implode('; ', $signals);

        return implode("\n", $lines);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parse(string $raw): array
    {
        $text = trim($raw);
        $text = (string) preg_replace('/^```[a-z]*|```$/m', '', $text);

        $data = json_decode(trim($text), true);

        if (! is_array($data)) {
            return [];
        }

        $out = [];
        foreach ($data as $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $out[] = [
                'severity' => $this->severity((string) ($row['severity'] ?? 'medium')),
                'title' => $title,
                'detail' => (string) ($row['detail'] ?? ''),
                'action' => (string) ($row['action'] ?? ''),
            ];
        }

        return array_slice($out, 0, 6);
    }

    private function severity(string $value): string
    {
        return in_array($value, [Gap::HIGH, Gap::MEDIUM, Gap::LOW, Gap::OK], true) ? $value : Gap::MEDIUM;
    }

    private function key(string $tenantId, AnalyticsRange $range): string
    {
        return "lead-insights:{$tenantId}:{$range->cacheKey()}";
    }
}
