<?php

declare(strict_types=1);

namespace App\Http\Requests\Cabinet;

use App\Enums\MemberPermission;
use App\Http\Requests\AbstractFormRequest;
use Illuminate\Validation\Rule;

/**
 * Добавление сотрудника (оператора) бизнеса. Доступно только владельцу.
 */
final class StoreTeamMemberRequest extends AbstractFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(MemberPermission::values())],
        ];
    }
}
