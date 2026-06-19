<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Enums\CrmProvider;
use App\Models\CrmConnection;
use App\Models\Tenant;
use App\Models\YclientsLink;
use App\Services\YclientsMarketplaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class YclientsMarketplaceServiceTest extends TestCase
{
    use RefreshDatabase;

    private YclientsMarketplaceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(YclientsMarketplaceService::class);
    }

    /**
     * @return CrmConnection|null подключение тенанта вне тенант-скоупа (для проверок)
     */
    private function connectionFor(Tenant $tenant): ?CrmConnection
    {
        return CrmConnection::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('provider', CrmProvider::Yclients->value)
            ->first();
    }

    public function test_claim_then_webhook_materializes_active_connection(): void
    {
        $tenant = Tenant::factory()->max()->create();

        // Бизнес вернулся из маркетплейса (токена ещё нет) — подключения нет.
        $this->service->claimSalon('1989012', $tenant->id);
        $this->assertNull($this->connectionFor($tenant));

        // Пришёл вебхук с user-токеном — материализуем рабочее подключение.
        $this->service->ingestWebhook(['salon_id' => '1989012', 'user_token' => 'user-tok', 'event' => 'install']);

        $connection = $this->connectionFor($tenant);
        $this->assertNotNull($connection);
        $this->assertTrue($connection->is_active);
        $this->assertSame('1989012', $connection->credential('company_id'));
        $this->assertSame('user-tok', $connection->credential('api_token'));
        $this->assertSame('marketplace', $connection->settings['source'] ?? null);
    }

    public function test_webhook_then_claim_materializes_active_connection(): void
    {
        $tenant = Tenant::factory()->max()->create();

        // Вебхук пришёл раньше привязки — токен застейджен, тенанта ещё нет.
        $this->service->ingestWebhook(['salon_id' => '777', 'user_token' => 'tok-777']);
        $this->assertNull($this->connectionFor($tenant));
        $this->assertDatabaseHas('yclients_links', ['salon_id' => '777', 'tenant_id' => null]);

        // Бизнес привязал филиал — материализация по уже сохранённому токену.
        $this->service->claimSalon('777', $tenant->id);

        $connection = $this->connectionFor($tenant);
        $this->assertNotNull($connection);
        $this->assertSame('tok-777', $connection->credential('api_token'));
    }

    public function test_webhook_without_claimed_tenant_only_stages_token(): void
    {
        $this->service->ingestWebhook(['salon_id' => '555', 'user_token' => 'tok-555']);

        $link = YclientsLink::query()->where('salon_id', '555')->firstOrFail();
        $this->assertNull($link->tenant_id);
        $this->assertSame('tok-555', $link->user_token);
        $this->assertNull($link->connected_at);
        $this->assertSame(0, CrmConnection::withoutGlobalScopes()->count());
    }

    public function test_disconnect_removes_connection_and_clears_token(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $this->service->claimSalon('1989012', $tenant->id);
        $this->service->ingestWebhook(['salon_id' => '1989012', 'user_token' => 'user-tok']);
        $this->assertNotNull($this->connectionFor($tenant));

        $this->service->disconnect('1989012');

        $this->assertNull($this->connectionFor($tenant));
        $link = YclientsLink::query()->where('salon_id', '1989012')->firstOrFail();
        $this->assertNull($link->user_token);
        $this->assertNull($link->connected_at);
    }

    public function test_uninstall_event_disconnects(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $this->service->claimSalon('1989012', $tenant->id);
        $this->service->ingestWebhook(['salon_id' => '1989012', 'user_token' => 'user-tok']);
        $this->assertNotNull($this->connectionFor($tenant));

        // Событие отключения в вебхуке — деактивируем, не создаём подключение.
        $this->service->ingestWebhook(['salon_id' => '1989012', 'event' => 'uninstall']);

        $this->assertNull($this->connectionFor($tenant));
    }

    public function test_tenant_without_crm_right_is_not_materialized(): void
    {
        // Тариф без права на CRM (по умолчанию) — даже при привязке и токене
        // рабочее подключение не создаётся (жёсткий рубеж в materialize).
        $tenant = Tenant::factory()->create();

        $this->service->claimSalon('1989012', $tenant->id);
        $this->service->ingestWebhook(['salon_id' => '1989012', 'user_token' => 'user-tok']);

        $this->assertNull($this->connectionFor($tenant));
    }

    public function test_user_token_path_is_extracted_from_nested_payload(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $this->service->claimSalon('42', $tenant->id);

        // Токен может прийти вложенным (data.user.user_token) — парсер это покрывает.
        $this->service->ingestWebhook(['data' => ['salon_id' => '42', 'user' => ['user_token' => 'nested-tok']]]);

        $connection = $this->connectionFor($tenant);
        $this->assertNotNull($connection);
        $this->assertSame('nested-tok', $connection->credential('api_token'));
    }
}
