<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests;

use App\Shared\Enums\TenantPlan;
use App\Shared\Http\AbstractFormRequest;
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
