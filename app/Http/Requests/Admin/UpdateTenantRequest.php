<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\TenantPlan;
use App\Http\Requests\AbstractFormRequest;
use Illuminate\Validation\Rule;

/**
 * Обновление подписки тенанта супер-админом: тариф и срок доступа.
 */
final class UpdateTenantRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan' => ['required', Rule::enum(TenantPlan::class)],
            'access_expires_at' => ['nullable', 'date'],
        ];
    }
}
