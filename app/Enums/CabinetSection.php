<?php

declare(strict_types=1);

namespace App\Enums;

use App\DTO\PlanFeatures;
use App\Enums\Contracts\HasLabel;

/**
 * Раздел кабинета, доступ к которому бизнес может ограничивать своим
 * сотрудникам (операторам). Значение совпадает со вторым сегментом имени
 * маршрута (`cabinet.<section>.…`) — по нему гейтит EnsureSectionAllowed.
 */
enum CabinetSection: string implements HasLabel
{
    case Conversations = 'conversations';
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

    public function label(): string
    {
        return match ($this) {
            self::Conversations => 'Лиды',
            self::Clients => 'База клиентов',
            self::Broadcasts => 'Рассылки',
            self::Scenarios => 'Сценарии',
            self::Knowledge => 'База знаний',
            self::Analytics => 'Аналитика',
            self::Channels => 'Каналы',
            self::Profile => 'Профиль бизнеса',
            self::Widget => 'Виджет на сайт',
            self::Notifications => 'Уведомления и эскалация',
            self::Integrations => 'YClients',
            self::Testing => 'Тестирование бота',
            self::Menu => 'Главное меню бота',
        };
    }

    /**
     * Возможность тарифа, без которой раздел недоступен бизнесу (ключ
     * {@see PlanFeatures}). null — раздел есть на любом тарифе. Связывает
     * тенантную матрицу (PlanFeatures) с матрицей мемберов.
     */
    public function requiredFeature(): ?string
    {
        return match ($this) {
            self::Analytics => 'analytics',
            self::Clients => 'clientBase',
            self::Broadcasts => 'broadcasts',
            self::Scenarios => 'flows',
            self::Integrations => 'crm',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
