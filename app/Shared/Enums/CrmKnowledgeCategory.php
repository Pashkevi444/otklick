<?php

declare(strict_types=1);

namespace App\Shared\Enums;

use App\Shared\Enums\Contracts\HasLabel;

/**
 * Категория записи базы знаний, выгруженной из CRM (нередактируемой).
 */
enum CrmKnowledgeCategory: string implements HasLabel
{
    case Service = 'service';
    case Staff = 'staff';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Service => 'Услуга',
            self::Staff => 'Мастер',
            self::Company => 'Филиал',
        };
    }
}
