<?php

declare(strict_types=1);

namespace App\Enums;

use App\DTO\PlanFeatures;
use App\Enums\Contracts\HasLabel;

/**
 * Единый каталог прав сотрудника (то, что владелец выдаёт галочками в «Команде»).
 * Два вида прав, оба хранятся в `users.permissions`:
 *  - доступ к разделу кабинета (значение = ключ {@see CabinetSection}, напр. `clients`);
 *  - право на действие внутри раздела (напр. `clients.delete`).
 *
 * Владелец/супер-админ имеют все права. `CabinetSection` остаётся отдельно — он
 * описывает структуру кабинета (роутинг/навигация); каждый раздел представлен
 * здесь правом-доступом (синхронность проверяется тестом).
 */
enum MemberPermission: string implements HasLabel
{
    // Доступ к разделам (значение совпадает с CabinetSection).
    case Conversations = 'conversations';
    case Leads = 'leads';
    case Deals = 'deals';
    case Clients = 'clients';
    case Broadcasts = 'broadcasts';
    case Scenarios = 'scenarios';
    case Knowledge = 'knowledge';
    case Analytics = 'analytics';
    case Channels = 'channels';
    case Profile = 'profile';
    case Widget = 'widget';
    case Notifications = 'notifications';
    case Integrations = 'integrations';
    case Testing = 'testing';
    case Menu = 'menu';

    // Права на действия внутри разделов.
    case ConversationsEdit = 'conversations.edit';
    case ConversationsDelete = 'conversations.delete';
    case ClientsEdit = 'clients.edit';
    case ClientsDelete = 'clients.delete';
    case KnowledgeEdit = 'knowledge.edit';
    case LeadsEdit = 'leads.edit';
    case DealsEdit = 'deals.edit';

    public function label(): string
    {
        return match ($this) {
            self::ConversationsEdit => 'Оператор: перехват и ответ в диалоге',
            self::ConversationsDelete => 'Удаление диалогов',
            self::ClientsEdit => 'Редактирование клиентов',
            self::ClientsDelete => 'Удаление клиентов',
            self::KnowledgeEdit => 'Редактирование базы знаний',
            self::LeadsEdit => 'Редактирование лидов',
            self::DealsEdit => 'Редактирование сделок',
            // Право-доступ к разделу — берём подпись раздела.
            default => $this->section()->label(),
        };
    }

    /** Право на действие (а не на доступ к разделу). */
    public function isAction(): bool
    {
        return str_contains($this->value, '.');
    }

    /** Раздел, к которому относится право (для группировки в UI и гейтинга). */
    public function section(): CabinetSection
    {
        return match ($this) {
            self::Conversations, self::ConversationsEdit, self::ConversationsDelete => CabinetSection::Conversations,
            self::Leads, self::LeadsEdit => CabinetSection::Leads,
            self::Deals, self::DealsEdit => CabinetSection::Deals,
            self::Clients, self::ClientsEdit, self::ClientsDelete => CabinetSection::Clients,
            self::Broadcasts => CabinetSection::Broadcasts,
            self::Scenarios => CabinetSection::Scenarios,
            self::Knowledge, self::KnowledgeEdit => CabinetSection::Knowledge,
            self::Analytics => CabinetSection::Analytics,
            self::Channels => CabinetSection::Channels,
            self::Profile => CabinetSection::Profile,
            self::Widget => CabinetSection::Widget,
            self::Notifications => CabinetSection::Notifications,
            self::Integrations => CabinetSection::Integrations,
            self::Testing => CabinetSection::Testing,
            self::Menu => CabinetSection::Menu,
        };
    }

    /**
     * Все права на действия в разделе (для группировки чекбоксов под доступом).
     *
     * @return list<self>
     */
    public static function actionsFor(CabinetSection $section): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $p): bool => $p->isAction() && $p->section() === $section,
        ));
    }

    /** Доступно ли это право бизнесу с такими возможностями тарифа (матрица СУ). */
    public function availableWith(PlanFeatures $features): bool
    {
        $feature = $this->section()->requiredFeature();

        return $feature === null || $features->has($feature);
    }

    /**
     * Права, которые бизнес с такими возможностями вправе раздавать сотрудникам
     * (матрица мемберов ⊆ тенантной матрицы). Владелец не может выдать доступ к
     * разделу/действию, которого нет в тарифе.
     *
     * @return list<self>
     */
    public static function grantableWith(PlanFeatures $features): array
    {
        return array_values(array_filter(self::cases(), fn (self $p): bool => $p->availableWith($features)));
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $p): string => $p->value, self::cases());
    }
}
