<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasLabel;

/**
 * Сущность CRM, к которой относится кастомное поле: лид или сделка.
 */
enum CustomFieldEntity: string implements HasLabel
{
    case Lead = 'lead';
    case Deal = 'deal';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Лиды',
            self::Deal => 'Сделки',
        };
    }

    /** Право-действие, дающее управление полями этой сущности. */
    public function editPermission(): string
    {
        return match ($this) {
            self::Lead => 'leads.edit',
            self::Deal => 'deals.edit',
        };
    }
}
