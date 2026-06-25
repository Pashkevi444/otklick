<?php

use App\Modules\Analytics\AnalyticsServiceProvider;
use App\Modules\Booking\BookingServiceProvider;
use App\Modules\Bot\BotServiceProvider;
use App\Modules\Broadcasts\BroadcastsServiceProvider;
use App\Modules\Channels\ChannelsServiceProvider;
use App\Modules\Clients\ClientsServiceProvider;
use App\Modules\Conversations\ConversationsServiceProvider;
use App\Modules\Flows\FlowsServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Knowledge\KnowledgeServiceProvider;
use App\Modules\Notifications\NotificationsServiceProvider;
use App\Modules\Platform\PlatformServiceProvider;
use App\Modules\Sandbox\SandboxServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\PgvectorServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    PgvectorServiceProvider::class,
    TenancyServiceProvider::class,

    // Модули (App\Modules\*) — каждый сам регистрирует свои биндинги/команды/события.
    // Привязки репозиториев живут в провайдере своего модуля (центрального больше нет).
    AnalyticsServiceProvider::class,
    NotificationsServiceProvider::class,
    BroadcastsServiceProvider::class,
    SandboxServiceProvider::class,
    KnowledgeServiceProvider::class,
    FlowsServiceProvider::class,
    BookingServiceProvider::class,
    ClientsServiceProvider::class,
    ChannelsServiceProvider::class,
    ConversationsServiceProvider::class,
    BotServiceProvider::class,
    IdentityServiceProvider::class,
    PlatformServiceProvider::class,
];
