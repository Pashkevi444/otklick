<?php

declare(strict_types=1);

namespace App\Modules\Booking;

use App\Modules\Booking\Console\ReconcileBookings;
use App\Modules\Booking\Console\SendAppointmentReminders;
use App\Modules\Booking\Contracts\BookingApi;
use App\Modules\Booking\Crm\CrmGatewayResolver;
use App\Modules\Booking\Crm\Yclients\YclientsGateway;
use App\Modules\Booking\Repositories\Contracts\CrmConnectionRepositoryInterface;
use App\Modules\Booking\Repositories\Eloquent\EloquentCrmConnectionRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Модуль «Запись» (порт записи в CRM бизнеса): автомат записи BookingFlow + порт
 * CRM (App\Modules\Booking\Crm — стратегии по провайдеру, сейчас YClients) + синк
 * подключения, маркетплейс YClients, сверка/напоминания. Реестр CRM-стратегий и
 * команды модуля регистрирует сам модуль. amoCRM и пр. — будущий ExternalCrm-порт.
 */
final class BookingServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        CrmConnectionRepositoryInterface::class => EloquentCrmConnectionRepository::class,
        BookingApi::class => BookingApiService::class,
    ];

    public function register(): void
    {
        $this->app->singleton(YclientsGateway::class, fn (): YclientsGateway => new YclientsGateway(
            (string) config('services.yclients.api_url'),
            config('services.yclients.partner_token'),
        ));

        // Реестр CRM-стратегий: новый CRM добавляется в этот тег.
        $this->app->tag([YclientsGateway::class], 'crm.gateways');
        $this->app->singleton(
            CrmGatewayResolver::class,
            fn ($app): CrmGatewayResolver => new CrmGatewayResolver($app->tagged('crm.gateways')),
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ReconcileBookings::class, SendAppointmentReminders::class]);
        }
    }
}
