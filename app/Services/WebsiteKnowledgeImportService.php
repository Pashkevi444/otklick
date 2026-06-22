<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\KnowledgeEntryData;
use App\Jobs\ImportKnowledgeFromSite;
use App\Llm\Contracts\LlmClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Импорт базы знаний с сайта бизнеса. Тянет несколько ключевых страниц, очищает
 * HTML до текста и через LLM превращает его в самостоятельные элементы базы
 * знаний (заголовок + ответ). Всё создаётся **черновиками** (`is_published=false`)
 * — бизнес проверяет и публикует то, что нужно. Запускается в фоне (см.
 * {@see ImportKnowledgeFromSite}).
 */
final readonly class WebsiteKnowledgeImportService
{
    /** Максимум страниц сайта за один импорт (корень + внутренние). */
    private const int MAX_PAGES = 8;

    /** Потолок созданных черновиков, чтобы импорт не «взорвал» базу знаний. */
    private const int MAX_ENTRIES = 60;

    /** Сколько текста страницы отдаём модели (символов). */
    private const int MAX_PAGE_CHARS = 6000;

    /** Короче — страница неинформативна, пропускаем. */
    private const int MIN_PAGE_CHARS = 200;

    private const string SYSTEM_PROMPT = <<<'PROMPT'
        Ты помогаешь бизнесу собрать базу знаний для чат-бота из текста его сайта.
        На вход — текст одной страницы сайта. Выдели из него полезные для клиентов
        факты (услуги, цены, условия, часы работы, адрес, доставка, частые вопросы)
        и оформи как самостоятельные элементы базы знаний.

        Верни СТРОГО JSON-массив объектов вида {"title": "...", "content": "..."} без
        пояснений и без markdown. Требования:
        - title — короткая тема (как вопрос в FAQ), до 80 символов;
        - content — понятный готовый ответ клиенту на русском, 1–4 предложения;
        - каждый элемент самодостаточен (не ссылается на «выше/ниже»);
        - игнорируй меню, футер, юридический шаблон, cookie-баннеры, дубли;
        - если полезного нет — верни пустой массив [].
        PROMPT;

    public function __construct(
        private KnowledgeBaseService $knowledge,
        private LlmClient $llm,
    ) {}

    /**
     * Импортирует базу знаний с сайта. Возвращает число созданных черновиков.
     *
     * @param  callable(int $percent, int $created): void|null  $onProgress
     */
    public function import(string $url, ?callable $onProgress = null): int
    {
        $root = $this->normalizeUrl($url);
        $progress = $onProgress ?? static fn (int $p, int $c): null => null;

        Log::info('site-import: начат', ['url' => $root]);

        $rootHtml = $this->fetch($root);
        $progress(8, 0);

        $pages = $this->collectPages($root, $rootHtml ?? '');
        $total = max(1, count($pages));

        $created = 0;
        $seenTitles = [];

        foreach ($pages as $i => $page) {
            try {
                $html = $page === $root ? $rootHtml : $this->fetch($page);
                $text = $html !== null ? $this->htmlToText($html) : '';

                if (mb_strlen($text) >= self::MIN_PAGE_CHARS) {
                    foreach ($this->structure($text) as $item) {
                        if ($created >= self::MAX_ENTRIES) {
                            break;
                        }

                        $key = mb_strtolower(trim($item['title']));
                        if ($key === '' || isset($seenTitles[$key])) {
                            continue;
                        }
                        $seenTitles[$key] = true;

                        $this->knowledge->create(new KnowledgeEntryData(
                            title: $item['title'],
                            content: $item['content'],
                            isPublished: false,
                        ));
                        $created++;
                    }
                }
            } catch (Throwable $e) {
                // Одна сбойная страница не должна валить весь импорт.
                Log::warning('site-import: страница пропущена', ['page' => $page, 'error' => $e->getMessage()]);
            }

            $progress((int) min(99, 8 + (int) round(($i + 1) / $total * 91)), $created);

            if ($created >= self::MAX_ENTRIES) {
                break;
            }
        }

        Log::info('site-import: завершён', ['url' => $root, 'created' => $created]);

        return $created;
    }

    /**
     * Список страниц для разбора: корень + до MAX_PAGES-1 внутренних ссылок,
     * приоритет — информативным разделам (услуги/цены/о нас/контакты/доставка).
     *
     * @return list<string>
     */
    private function collectPages(string $root, string $html): array
    {
        $host = (string) parse_url($root, PHP_URL_HOST);
        $links = [];

        preg_match_all('/href\s*=\s*["\']([^"\'#]+)["\']/i', $html, $matches);

        foreach ($matches[1] as $href) {
            $abs = $this->absolutize($root, trim($href));
            if ($abs === null || $abs === $root) {
                continue;
            }
            if (parse_url($abs, PHP_URL_HOST) !== $host) {
                continue; // только тот же домен
            }
            $links[$abs] = $this->linkScore($abs);
        }

        arsort($links);
        $internal = array_slice(array_keys($links), 0, self::MAX_PAGES - 1);

        return array_values(array_unique([$root, ...$internal]));
    }

    /**
     * Приоритет ссылки по ключевым словам в пути — выше для полезных разделов.
     */
    private function linkScore(string $url): int
    {
        $path = mb_strtolower((string) parse_url($url, PHP_URL_PATH));
        $weights = [
            'price' => 5, 'tarif' => 5, 'cen' => 5, 'usl' => 5, 'service' => 5,
            'about' => 4, 'o-nas' => 4, 'o-kompanii' => 4, 'dostavk' => 4, 'delivery' => 4,
            'contact' => 3, 'kontakt' => 3, 'faq' => 3, 'vopros' => 3, 'oplat' => 3, 'payment' => 3,
        ];

        $score = 1;
        foreach ($weights as $needle => $weight) {
            if (str_contains($path, $needle)) {
                $score += $weight;
            }
        }

        // Глубокие/мусорные пути — ниже.
        $score -= substr_count(trim($path, '/'), '/');

        return $score;
    }

    /**
     * Превращает текст страницы в элементы базы знаний через LLM.
     *
     * @return list<array{title: string, content: string}>
     */
    private function structure(string $text): array
    {
        try {
            $raw = $this->llm->generate(self::SYSTEM_PROMPT, [
                ['role' => 'user', 'content' => mb_substr($text, 0, self::MAX_PAGE_CHARS)],
            ]);
        } catch (Throwable $e) {
            Log::warning('site-import: LLM недоступна', ['error' => $e->getMessage()]);

            return [];
        }

        $clean = trim((string) preg_replace('/^```[a-z]*|```$/m', '', trim($raw)));
        $data = json_decode($clean, true);

        if (! is_array($data)) {
            return [];
        }

        $items = [];
        foreach ($data as $row) {
            if (! is_array($row) || ! isset($row['title'], $row['content'])) {
                continue;
            }

            $title = trim((string) $row['title']);
            $content = trim((string) $row['content']);

            if ($title === '' || $content === '') {
                continue;
            }

            $items[] = [
                'title' => mb_substr($title, 0, 120),
                'content' => mb_substr($content, 0, 2000),
            ];
        }

        return $items;
    }

    private function fetch(string $url): ?string
    {
        try {
            $response = Http::connectTimeout(5)
                ->timeout(20)
                ->withHeaders(['User-Agent' => 'OtklikBot/1.0 (+https://otcl1ck.ru)'])
                ->get($url);

            return $response->successful() ? $response->body() : null;
        } catch (Throwable $e) {
            Log::warning('site-import: страница не загрузилась', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * HTML → читаемый текст: вырезаем скрипты/стили, теги, схлопываем пробелы.
     */
    private function htmlToText(string $html): string
    {
        $text = (string) preg_replace('#<(script|style|noscript|svg)\b[^>]*>.*?</\1>#is', ' ', $html);
        $text = (string) preg_replace('#<[^>]+>#', ' ', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        return rtrim($url, '/');
    }

    /**
     * Приводит ссылку к абсолютному виду; возвращает null для не-http(s)/мусора.
     */
    private function absolutize(string $base, string $href): ?string
    {
        if ($href === '' || preg_match('#^(mailto:|tel:|javascript:|data:)#i', $href)) {
            return null;
        }

        if (preg_match('#^https?://#i', $href)) {
            return rtrim($href, '/');
        }

        if (str_starts_with($href, '//')) {
            $scheme = (string) parse_url($base, PHP_URL_SCHEME);

            return rtrim($scheme.':'.$href, '/');
        }

        $scheme = parse_url($base, PHP_URL_SCHEME);
        $host = parse_url($base, PHP_URL_HOST);
        if ($scheme === null || $host === null) {
            return null;
        }

        $path = str_starts_with($href, '/') ? $href : '/'.$href;

        return rtrim("{$scheme}://{$host}{$path}", '/');
    }
}
