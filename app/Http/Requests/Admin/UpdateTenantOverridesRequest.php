<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\AbstractFormRequest;

/**
 * Индивидуальные права/лимиты бизнеса (оверрайды поверх тарифа), задаёт супер-админ.
 */
final class UpdateTenantOverridesRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'crm' => ['required', 'boolean'],
            'analytics' => ['required', 'boolean'],
            'broadcasts' => ['required', 'boolean'],
            'clientBase' => ['required', 'boolean'],
            'allChannels' => ['required', 'boolean'],
            'webWidget' => ['required', 'boolean'],
            'maxOperators' => ['required', 'integer', 'min:0', 'max:999'],
            'maxNotifyEmail' => ['required', 'integer', 'min:0', 'max:999'],
            'maxNotifyTelegram' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function overrides(): array
    {
        return $this->only([
            'crm', 'analytics', 'broadcasts', 'clientBase', 'allChannels', 'webWidget',
            'maxOperators', 'maxNotifyEmail', 'maxNotifyTelegram',
        ]);
    }
}
