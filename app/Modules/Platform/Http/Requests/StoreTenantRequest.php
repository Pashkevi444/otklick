<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests;

use App\Http\Requests\AbstractFormRequest;
use App\Shared\Enums\TenantPlan;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Создание тенанта-бизнеса вместе с владельцем (только супер-админ).
 */
final class StoreTenantRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'plan' => ['required', Rule::enum(TenantPlan::class)],
            'access_expires_at' => ['nullable', 'date'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'owner_password' => ['required', 'string', Password::min(8)],
        ];
    }
}
