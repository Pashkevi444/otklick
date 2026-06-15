<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\AbstractFormRequest;

/**
 * Запрос кода восстановления пароля на email.
 */
final class SendPasswordResetCodeRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }
}
