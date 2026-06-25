<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Http\Requests\AbstractFormRequest;
use App\Shared\Enums\MemberPermission;
use Illuminate\Validation\Rule;

/**
 * Изменение прав (и имени) сотрудника бизнеса. Доступно только владельцу.
 */
final class UpdateTeamMemberRequest extends AbstractFormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(MemberPermission::values())],
        ];
    }
}
