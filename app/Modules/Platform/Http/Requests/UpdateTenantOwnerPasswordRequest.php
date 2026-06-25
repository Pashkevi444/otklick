<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests;

use App\Http\Requests\AbstractFormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Установка супер-админом нового пароля владельцу бизнеса.
 */
final class UpdateTenantOwnerPasswordRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
