<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Shared\Http\AbstractFormRequest;
use Illuminate\Validation\Rule;

/**
 * Запрос на смену e-mail: новый адрес (свободный) + текущий пароль для безопасности.
 */
final class RequestEmailChangeRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // unique:users,email отвергает и чужие адреса, и собственный текущий.
            'new_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'current_password' => ['required', 'current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'new_email.unique' => 'Этот адрес уже используется.',
            'current_password.current_password' => 'Неверный текущий пароль.',
        ];
    }
}
