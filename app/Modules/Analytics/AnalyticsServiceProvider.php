<?php

declare(strict_types=1);

namespace App\Modules\Analytics;

use App\Modules\Analytics\Console\SendWeeklyAnalyticsDigest;
use App\Modules\Analytics\Repositories\Contracts\LeadAnalyticsRepositoryInterface;
use App\Modules\Analytics\Repositories\Eloquent\EloquentLeadAnalyticsRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Модуль «Аналитика»: воронка/метрики/ценностный отчёт + недельный AI-дайджест.
 * Биндинги репозиториев и команды модуля регистрируются здесь — модуль
 * самодостаточен. Данные диалогов/клиентов читает через
 * {@see LeadAnalyticsRepositoryInterface} (шов владения данными — в микросервисах
 * станет read-API); сервисы CRM/Conversations напрямую не дёргает.
 */
final class AnalyticsServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        LeadAnalyticsRepositoryInterface::class => EloquentLeadAnalyticsRepository::class,
    ];

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SendWeeklyAnalyticsDigest::class]);
        }
    }
}
