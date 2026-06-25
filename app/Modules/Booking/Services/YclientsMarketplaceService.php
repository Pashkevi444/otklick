<?php

declare(strict_types=1);

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Models\YclientsLink;
use App\Shared\Enums\CrmProvider;
use App\Shared\Models\Tenant;
use App\Shared\Tenancy\TenantInitializer;
use Illuminate\Support\Facades\Log;

/**
 * Подключение YClients через маркетплейс (OAuth-подобный флоу «Подключить»).
 *
 * Факты приходят двумя путями и в любом порядке:
 *  - бизнес возвращается на Registration Redirect уже залогиненным → знаем тенанта
 *    ({@see claimSalon});
 *  - YClients присылает server-to-server вебхук с salon_id и user-токеном
 *    ({@see ingestWebhook}).
 *
 * Промежуточные факты складываем в {@see YclientsLink} (вне тенант-изоляции —
 * вебхук приходит без контекста). Как только известны И тенант, И user-токен —
 * материализуем рабочее подключение в crm_connections под контекстом тенанта.
 */
final readonly class YclientsMarketplaceService
{
    public function __construct(
        private TenantInitializer $tenancy,
        private CrmConnectionService $crm,
    ) {}

    /**
     * Шаг Registration Redirect: залогиненный бизнес привязывает свой филиал.
     * Если user-токен уже пришёл вебхуком — сразу материализуем подключение.
     */
    public function claimSalon(string $salonId, string $tenantId): void
    {
        $link = YclientsLink::query()->firstOrNew(['salon_id' => $salonId]);
        $link->tenant_id = $tenantId;
        $link->save();

        Log::info('crm.yclients.marketplace.claim', [
            'salon_id' => $salonId,
            'tenant_id' => $tenantId,
            'has_token' => $this->hasToken($link),
        ]);

        if ($this->hasToken($link)) {
            $this->materialize($link);
        }
    }

    /**
     * Приём вебхука YClients о подключении/событиях. Идемпотентно. Если событие —
     * отключение, деактивируем подключение. Иначе сохраняем user-токен и, если
     * тенант уже привязан, материализуем подключение.
     *
     * @param  array<string, mixed>  $payload
     */
    public function ingestWebhook(array $payload): void
    {
        $salonId = $this->extractSalonId($payload);
        $event = $this->extractEvent($payload);

        Log::info('crm.yclients.marketplace.webhook', [
            'salon_id' => $salonId,
            'event' => $event,
            'keys' => array_keys($payload),
        ]);

        if ($salonId === null) {
            return;
        }

        if ($this->isUninstall($event)) {
            $this->disconnect($salonId);

            return;
        }

        $link = YclientsLink::query()->firstOrNew(['salon_id' => $salonId]);
        $link->raw = $payload;

        $userToken = $this->extractUserToken($payload);
        if ($userToken !== null) {
            $link->user_token = $userToken;
        }

        $link->save();

        if ($link->tenant_id !== null && $this->hasToken($link)) {
            $this->materialize($link);
        }
    }

    /**
     * Отключение интеграции (callback YClients или из кабинета): удаляем рабочее
     * подключение тенанта и забываем user-токен. Идемпотентно.
     */
    public function disconnect(string $salonId): void
    {
        $link = YclientsLink::query()->where('salon_id', $salonId)->first();

        if ($link === null) {
            return;
        }

        if ($link->tenant_id !== null) {
            $this->tenancy->run($link->tenant_id, function (): void {
                $this->crm->disconnect(CrmProvider::Yclients);
            });
        }

        $link->user_token = null;
        $link->connected_at = null;
        $link->save();

        Log::info('crm.yclients.marketplace.disconnect', [
            'salon_id' => $salonId,
            'tenant_id' => $link->tenant_id,
        ]);
    }

    /**
     * Создаёт/обновляет рабочее подключение тенанта к YClients под его контекстом.
     */
    private function materialize(YclientsLink $link): void
    {
        $tenantId = $link->tenant_id;

        if ($tenantId === null) {
            return;
        }

        // Жёсткий рубеж по праву на CRM: даже если филиал был привязан, а право
        // позже сняли (даунгрейд тарифа) — рабочее подключение не создаём.
        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null || ! $tenant->features()->crm) {
            Log::info('crm.yclients.marketplace.skipped_no_crm_right', [
                'salon_id' => $link->salon_id,
                'tenant_id' => $tenantId,
            ]);

            return;
        }

        $salonId = $link->salon_id;
        $userToken = (string) $link->user_token;

        $this->tenancy->run($tenantId, function () use ($tenantId, $salonId, $userToken): void {
            $this->crm->connect($tenantId, CrmProvider::Yclients, [
                'company_id' => $salonId,
                'api_token' => $userToken,
            ], ['source' => 'marketplace']);
        });

        if ($link->connected_at === null) {
            $link->connected_at = now();
            $link->save();
        }

        Log::info('crm.yclients.marketplace.materialized', [
            'salon_id' => $salonId,
            'tenant_id' => $tenantId,
        ]);
    }

    private function hasToken(YclientsLink $link): bool
    {
        return $link->user_token !== null && $link->user_token !== '';
    }

    /**
     * Имена полей в вебхуке YClients задокументированы обрывочно, поэтому ищем
     * salon_id/user_token/событие по нескольким вероятным путям. Точный формат
     * подтверждается по первому реальному payload в логах (crm.yclients.marketplace.*).
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractSalonId(array $payload): ?string
    {
        foreach ([['salon_id'], ['company_id'], ['data', 'salon_id'], ['data', 'company_id'], ['salon', 'id'], ['company', 'id']] as $path) {
            $value = $this->dig($payload, $path);
            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractUserToken(array $payload): ?string
    {
        foreach ([['user_token'], ['data', 'user_token'], ['data', 'user', 'user_token'], ['user', 'user_token'], ['user', 'token'], ['data', 'user', 'token']] as $path) {
            $value = $this->dig($payload, $path);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractEvent(array $payload): ?string
    {
        foreach ([['event'], ['status'], ['type'], ['data', 'event']] as $path) {
            $value = $this->dig($payload, $path);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function isUninstall(?string $event): bool
    {
        if ($event === null) {
            return false;
        }

        $event = mb_strtolower($event);

        foreach (['uninstall', 'disconnect', 'delete', 'remov', 'freeze', 'deactiv'] as $needle) {
            if (str_contains($event, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Достаёт вложенное значение по пути ключей; null, если путь не существует.
     *
     * @param  array<string, mixed>  $array
     * @param  list<string>  $path
     */
    private function dig(array $array, array $path): mixed
    {
        $current = $array;

        foreach ($path as $key) {
            if (! is_array($current) || ! array_key_exists($key, $current)) {
                return null;
            }

            $current = $current[$key];
        }

        return $current;
    }
}
