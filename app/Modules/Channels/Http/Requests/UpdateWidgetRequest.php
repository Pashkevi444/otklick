<?php

declare(strict_types=1);

namespace App\Modules\Channels\Http\Requests;

use App\Http\Requests\AbstractFormRequest;

/**
 * Обновление настроек веб-виджета: список разрешённых доменов (по одному в строке).
 */
final class UpdateWidgetRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'origins' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
