<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests;

use App\Http\Requests\AbstractFormRequest;

/**
 * Редактирование контента публичного сайта (только супер-админ).
 */
final class UpdateSiteSettingsRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hero_title' => ['required', 'string', 'max:255'],
            'hero_subtitle' => ['required', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'telegram' => ['nullable', 'string', 'max:100'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'inn' => ['nullable', 'string', 'max:20'],
            'ogrnip' => ['nullable', 'string', 'max:20'],
            'access_note' => ['required', 'string', 'max:1000'],
        ];
    }
}
