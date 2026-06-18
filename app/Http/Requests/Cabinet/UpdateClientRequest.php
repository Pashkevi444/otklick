<?php

declare(strict_types=1);

namespace App\Http\Requests\Cabinet;

use App\Http\Requests\AbstractFormRequest;

/**
 * Ручное редактирование карточки клиента в кабинете. Все поля необязательны
 * (резюме генерирует LLM отдельно).
 */
final class UpdateClientRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'telegram_username' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
