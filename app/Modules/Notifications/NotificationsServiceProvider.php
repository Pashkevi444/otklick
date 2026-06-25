<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Notifications\Contracts\NotificationsApi;
use App\Modules\Notifications\Delivery\EmailNotifier;
use App\Modules\Notifications\Delivery\NotifierResolver;
use App\Modules\Notifications\Delivery\TelegramNotifier;
use App\Modules\Notifications\Repositories\Contracts\NotificationRecipientRepositoryInterface;
use App\Modules\Notifications\Repositories\Contracts\UserNotificationRepositoryInterface;
use App\Modules\Notifications\Repositories\Eloquent\EloquentNotificationRecipientRepository;
use App\Modules\Notifications\Repositories\Eloquent\EloquentUserNotificationRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Модуль «Уведомления»: in-app колокольчик (UserNotification, пер-юзер read-state)
 * + исходящие владельцу (NotificationRecipient → почта/Telegram через реестр
 * нотификаторов). Биндинги репозиториев и реестр нотификаторов регистрируются
 * здесь — модуль самодостаточен.
 */
final class NotificationsServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        UserNotificationRepositoryInterface::class => EloquentUserNotificationRepository::class,
        NotificationRecipientRepositoryInterface::class => EloquentNotificationRecipientRepository::class,
        NotificationsApi::class => NotificationsApiService::class,
    ];

    public function register(): void
    {
        // Реестр нотификаторов: новый канал уведомлений добавляется в этот тег.
        $this->app->tag([EmailNotifier::class, TelegramNotifier::class], 'notifiers');
        $this->app->singleton(
            NotifierResolver::class,
            fn ($app): NotifierResolver => new NotifierResolver($app->tagged('notifiers')),
        );
    }
}
