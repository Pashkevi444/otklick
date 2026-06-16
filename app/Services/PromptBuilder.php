<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\BusinessProfile;
use App\Models\KnowledgeEntry;
use Illuminate\Support\Collection;

/**
 * Собирает системный промпт для бота из профиля бизнеса и базы знаний.
 * Записи БЗ выводятся строками с префиксом «• », чтобы модель (и локальный fake)
 * однозначно опирались на знания тенанта.
 */
final class PromptBuilder
{
    /** Сентинел: модель возвращает его, когда ответа нет в данных. */
    public const string ESCALATE = '[[ESCALATE]]';

    /**
     * @param  Collection<int, KnowledgeEntry>  $entries
     */
    public function build(string $businessName, BusinessProfile $profile, Collection $entries): string
    {
        $sections = [];

        $sections[] = "Ты — Алиса, AI-администратор бизнеса «{$businessName}». Общаешься с клиентами в ".
            'мессенджере от лица бизнеса: тепло, вежливо, на «вы», кратко (1–3 предложения), по-русски — '.
            "как живой внимательный администратор.\n".
            "Веди себя так:\n".
            '— Отвечай ТОЛЬКО на основе сведений ниже. Никогда ничего не выдумывай (цены, услуги, адреса, акции — строго из данных).'."\n".
            '— Сведения в базе знаний — это заметки и инструкции владельца, иногда в форме «если спросят про X — ответь Y». '.
            'Понимай их смысл и отвечай клиенту СВОИМИ словами от лица бизнеса. НЕ копируй текст заметок дословно, '.
            "НЕ показывай служебные пометки и НЕ повторяй вопрос клиента.\n".
            '— НИКОГДА не пиши плейсхолдеры или текст в квадратных скобках (например «[вставить фото]», «[ссылка]»). '.
            'Если клиент просит примеры работ или фото: пришли реальные ссылки на фото из данных (если они есть); '.
            "если фото в данных нет — предложи прислать примеры или записаться, но не выдумывай ссылку.\n".
            '— Мягко веди клиента к записи: предложи подходящую услугу, уточни удобные дату и время, затем имя и телефон. '.
            "Не бросай клиента на полуслове, задавай один уточняющий вопрос за раз.\n".
            '— Если ответа нет в сведениях, клиент просит позвать человека, это жалоба или нестандартная ситуация — '.
            'верни ровно '.self::ESCALATE." и больше ничего.\n".
            '— Пиши по-человечески и профессионально, без канцелярита и markdown-разметки.';

        $about = $this->about($profile);
        if ($about !== []) {
            $sections[] = "О бизнесе:\n".implode("\n", array_map(fn (string $l): string => "- {$l}", $about));
        }

        if ($profile->escalationNote !== null && $profile->escalationNote !== '') {
            $sections[] = 'Передавай вопрос администратору (верни '.self::ESCALATE.') в этих случаях: '.$profile->escalationNote;
        }

        $sections[] = $entries->isEmpty()
            ? 'База знаний пуста.'
            : "База знаний:\n".$entries->map(fn (KnowledgeEntry $e): string => '• '.$this->entryLine($e))->implode("\n");

        return implode("\n\n", $sections);
    }

    /**
     * @return list<string>
     */
    private function about(BusinessProfile $profile): array
    {
        return array_values(array_filter([
            $profile->phone !== null && $profile->phone !== '' ? "Телефон: {$profile->phone}" : null,
            $profile->address !== null && $profile->address !== '' ? "Адрес: {$profile->address}" : null,
            $profile->workingHours !== null && $profile->workingHours !== '' ? "Часы работы: {$profile->workingHours}" : null,
        ], fn (?string $l): bool => $l !== null));
    }

    private function entryLine(KnowledgeEntry $entry): string
    {
        $line = "{$entry->title}: {$entry->content}";

        if ($entry->links !== []) {
            $links = implode('; ', array_map(
                fn (array $l): string => "{$l['label']} — {$l['url']}",
                $entry->links,
            ));
            $line .= " (ссылки: {$links})";
        }

        if (! empty($entry->images)) {
            $images = implode('; ', array_map(
                fn (array $i): string => $this->absoluteUrl((string) $i['url']),
                $entry->images,
            ));
            $line .= " (фото примеров работ — отправляй эти ссылки клиенту по запросу: {$images})";
        }

        return $line;
    }

    private function absoluteUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }
}
