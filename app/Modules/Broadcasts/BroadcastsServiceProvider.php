<?php

declare(strict_types=1);

namespace App\Modules\Broadcasts;

use App\Modules\Broadcasts\Console\RunDueBroadcasts;
use App\Modules\Broadcasts\Repositories\Contracts\BroadcastRepositoryInterface;
use App\Modules\Broadcasts\Repositories\Eloquent\EloquentBroadcastRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Модуль «Рассылки»: ручные/расписанные рассылки по базе клиентов (мессенджеры +
 * почта). Доставка идёт через Channels (ChannelGatewayResolver) и Mail — это
 * межмодульные зависимости, импортируются явно. Биндинг репозитория и команду
 * запуска расписанных рассылок регистрирует сам модуль.
 */
final class BroadcastsServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        BroadcastRepositoryInterface::class => EloquentBroadcastRepository::class,
    ];

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([RunDueBroadcasts::class]);
        }
    }
}
