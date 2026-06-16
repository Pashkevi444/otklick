<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Http\Requests\AbstractFormRequest;

/**
 * Подтверждение смены e-mail кодом из письма.
 */
final class ConfirmEmailChangeRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'digits:6'],
        ];
    }
}
