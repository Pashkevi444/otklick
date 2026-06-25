<?php

declare(strict_types=1);

namespace App\Modules\Channels\Http\Requests;

use App\Http\Requests\AbstractFormRequest;

/**
 * Обновление оформления веб-виджета: цвет акцента (шапка/кнопка). Принимаем HEX
 * вида #RRGGBB; пусто — сброс на брендовый цвет «Отклик».
 */
final class UpdateWidgetAppearanceRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'color.regex' => 'Цвет должен быть в формате #RRGGBB, например #2E74B5.',
        ];
    }
}
