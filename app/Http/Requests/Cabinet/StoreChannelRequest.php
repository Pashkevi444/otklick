<?php

declare(strict_types=1);

namespace App\Http\Requests\Cabinet;

use App\Enums\ChannelType;
use App\Http\Requests\AbstractFormRequest;
use Illuminate\Validation\Rule;

/**
 * Подключение канала тенанта. Тип выбирается полем `type` (telegram/vk/max); для
 * обратной совместимости со старой формой при отсутствии типа считаем Telegram.
 *
 * Telegram: токен бота `<bot_id>:<secret>`.
 * ВКонтакте: токен сообщества + числовой id сообщества.
 * MAX: токен бота (от @MasterBot).
 */
final class StoreChannelRequest extends AbstractFormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('type')) {
            $this->merge(['type' => ChannelType::Telegram->value]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ChannelType::class)->only([ChannelType::Telegram, ChannelType::Vk, ChannelType::Max])],
            'bot_token' => ['required_if:type,telegram', 'nullable', 'string', 'regex:/^\d+:[A-Za-z0-9_-]+$/'],
            'access_token' => ['required_if:type,vk,max', 'nullable', 'string'],
            'group_id' => ['required_if:type,vk', 'nullable', 'string', 'regex:/^\d+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bot_token.required_if' => 'Укажите токен бота от @BotFather.',
            'bot_token.regex' => 'Токен бота должен быть в формате 123456:ABCdef...',
            'access_token.required_if' => 'Укажите токен — сообщества ВКонтакте или бота MAX.',
            'group_id.required_if' => 'Укажите id сообщества ВКонтакте.',
            'group_id.regex' => 'id сообщества — это число (без «club»/«public»).',
        ];
    }
}
