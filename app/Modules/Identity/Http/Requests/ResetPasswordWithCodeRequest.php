<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Http\Requests\AbstractFormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Смена пароля по коду из письма.
 */
final class ResetPasswordWithCodeRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
