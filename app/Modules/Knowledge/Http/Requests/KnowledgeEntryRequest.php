<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Http\Requests;

use App\Shared\Http\AbstractFormRequest;

/**
 * Создание/редактирование записи базы знаний (с ссылками и картинками).
 */
final class KnowledgeEntryRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'is_published' => ['boolean'],

            'links' => ['array', 'max:20'],
            'links.*.label' => ['required', 'string', 'max:255'],
            'links.*.url' => ['required', 'url', 'max:2000'],

            // Уже загруженные картинки, которые нужно оставить (пути на диске).
            'existing_images' => ['array', 'max:20'],
            'existing_images.*' => ['string'],

            // Новые загружаемые файлы.
            'images' => ['array', 'max:20'],
            'images.*' => ['image', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'links.*.label.required' => 'У ссылки должна быть подпись.',
            'links.*.url.required' => 'У ссылки должен быть адрес.',
            'links.*.url.url' => 'Адрес ссылки должен начинаться с http(s)://',
            'images.*.image' => 'Можно загружать только изображения.',
            'images.*.max' => 'Картинка не больше 5 МБ.',
        ];
    }
}
