<?php

declare(strict_types=1);

namespace App\Http\Requests\Cabinet;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Подключение Telegram-бота к каналу тенанта. Токен формата <bot_id>:<secret>.
 */
final class StoreChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bot_token' => ['required', 'string', 'regex:/^\d+:[A-Za-z0-9_-]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bot_token.regex' => 'Токен бота должен быть в формате 123456:ABCdef...',
        ];
    }
}
