<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Базовый Form Request. Авторизация по умолчанию разрешена — доступ
 * контролируют middleware (auth/tenant/super-admin) и проверки в контроллерах
 * (например, secret вебхука). Конкретные реквесты задают только правила.
 */
abstract class AbstractFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function rules(): array;
}
