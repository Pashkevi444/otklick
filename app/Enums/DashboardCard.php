<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasLabel;

/**
 * Каталог плашек дашборда — единый источник для редактора состояний у супер-админа
 * и для гейта «тех. работы». Значение совпадает с секцией cabinet-маршрута
 * (`cabinet.<key>.…`), чтобы middleware мог сопоставить открытый раздел с плашкой.
 */
enum DashboardCard: string implements HasLabel
{
    case Analytics = 'analytics';
    case Conversations = 'conversations';
    case Clients = 'clients';
    case Broadcasts = 'broadcasts';
    case Scenarios = 'scenarios';
    case Testing = 'testing';
    case Channels = 'channels';
    case Widget = 'widget';
    case Profile = 'profile';
    case Knowledge = 'knowledge';
    case Notifications = 'notifications';
    case Integrations = 'integrations';
    case Team = 'team';
    case Menu = 'menu';

    public function label(): string
    {
        return match ($this) {
            self::Analytics => 'Аналитика',
            self::Conversations => 'Лиды',
            self::Clients => 'База клиентов',
            self::Broadcasts => 'Рассылки',
            self::Scenarios => 'Сценарии',
            self::Testing => 'Тестирование бота',
            self::Channels => 'Каналы',
            self::Widget => 'Виджет на сайт',
            self::Profile => 'Профиль бизнеса',
            self::Knowledge => 'База знаний',
            self::Notifications => 'Уведомления и эскалация',
            self::Integrations => 'YClients',
            self::Team => 'Команда',
            self::Menu => 'Главное меню бота',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
