<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Http\Requests\AbstractFormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Смена собственного пароля пользователем.
 */
final class UpdatePasswordRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
