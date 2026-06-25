<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Http\Requests;

use App\Http\Requests\AbstractFormRequest;

/**
 * Запуск импорта базы знаний с сайта бизнеса: один URL. Принимаем только
 * http(s) — остальное (mailto/ftp/…) не сайт для парсинга.
 */
final class ImportSiteRequest extends AbstractFormRequest
{
    /**
     * Пользователь часто вводит адрес без схемы («mysite.ru») — подставляем
     * https://, чтобы валидация `url` прошла и сервису пришёл корректный адрес.
     */
    protected function prepareForValidation(): void
    {
        $url = trim((string) $this->input('url'));

        if ($url !== '' && ! preg_match('#^https?://#i', $url)) {
            $this->merge(['url' => 'https://'.$url]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2000', 'url:http,https'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.required' => 'Укажите адрес сайта.',
            'url.url' => 'Введите корректный адрес сайта, например https://mysite.ru.',
        ];
    }
}
