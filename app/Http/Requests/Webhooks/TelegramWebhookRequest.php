<?php

declare(strict_types=1);

namespace App\Http\Requests\Webhooks;

use App\Http\Requests\AbstractFormRequest;

/**
 * Мягкая валидация апдейта Telegram: проверяем лишь, что это похоже на апдейт
 * (есть update_id). Содержимое разбирает уже задача — апдейты бывают разных
 * типов, и вебхук не должен отклонять валидные из-за строгой схемы.
 */
final class TelegramWebhookRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'update_id' => ['required', 'integer'],
        ];
    }
}
