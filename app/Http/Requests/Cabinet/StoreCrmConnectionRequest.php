<?php

declare(strict_types=1);

namespace App\Http\Requests\Cabinet;

use App\Enums\CrmProvider;
use App\Http\Requests\AbstractFormRequest;
use App\Services\CrmConnectionService;

/**
 * Подключение CRM. Набор обязательных полей кредов диктует стратегия провайдера
 * (см. CrmGateway::credentialFields) — здесь нет знания о конкретной CRM.
 */
final class StoreCrmConnectionRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (app(CrmConnectionService::class)->credentialFields($this->provider()) as $field) {
            $rules["credentials.{$field->key}"] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }

    public function provider(): CrmProvider
    {
        return CrmProvider::from((string) $this->route('provider'));
    }
}
