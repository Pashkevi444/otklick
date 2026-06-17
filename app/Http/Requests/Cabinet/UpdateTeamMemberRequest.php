<?php

declare(strict_types=1);

namespace App\Http\Requests\Cabinet;

use App\Enums\CabinetSection;
use App\Http\Requests\AbstractFormRequest;
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
            'permissions.*' => [Rule::in(CabinetSection::values())],
        ];
    }
}
