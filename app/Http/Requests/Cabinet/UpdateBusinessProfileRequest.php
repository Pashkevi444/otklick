<?php

declare(strict_types=1);

namespace App\Http\Requests\Cabinet;

use App\Http\Requests\AbstractFormRequest;

/**
 * Обновление профиля бизнеса в кабинете тенанта.
 */
final class UpdateBusinessProfileRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'working_hours' => ['nullable', 'string', 'max:255'],
            'escalation_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
