<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Shared\Enums\MemberPermission;
use App\Shared\Http\AbstractFormRequest;
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
