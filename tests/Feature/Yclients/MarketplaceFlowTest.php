<?php

declare(strict_types=1);

namespace Tests\Feature\Yclients;

use App\Modules\Booking\Models\YclientsLink;
use App\Shared\Enums\CrmProvider;
use App\Shared\Models\Tenant;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MarketplaceFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantWithOwner(): array
    {
        // Тариф «Макс» — с правом на CRM (нужно для marketplace-подключения).
        $tenant = Tenant::factory()->max()->create();

        return [$tenant, User::factory()->owner($tenant)->create()];
    }

    public function test_connect_redirect_requires_auth(): void
    {
        $this->get('/yclients/connect?salon_id=123')->assertRedirect('/login');
    }

    public function test_connect_claims_salon_for_current_tenant(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)
            ->get('/yclients/connect?salon_id=1989012')
            ->assertRedirect(route('cabinet.integrations.index'));

        $this->assertDatabaseHas('yclients_links', [
            'salon_id' => '1989012',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_connect_without_salon_id_shows_error(): void
    {
        [, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)
            ->get('/yclients/connect')
            ->assertRedirect(route('cabinet.integrations.index'))
            ->assertSessionHas('error');
    }

    public function test_connect_without_crm_right_is_blocked(): void
    {
        // Тариф без права на CRM — привязка запрещена, редирект на подписку.
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)
            ->get('/yclients/connect?salon_id=1989012')
            ->assertRedirect(route('cabinet.subscription'))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('yclients_links', ['salon_id' => '1989012']);
    }

    public function test_webhook_is_public_and_stages_token(): void
    {
        $this->postJson('/yclients/webhook', ['salon_id' => '321', 'user_token' => 'tok-321'])
            ->assertNoContent();

        $link = YclientsLink::query()->where('salon_id', '321')->firstOrFail();
        $this->assertSame('tok-321', $link->user_token);
    }

    public function test_webhook_then_connect_creates_working_connection(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        // Вебхук пришёл раньше — застейджил токен.
        $this->postJson('/yclients/webhook', ['salon_id' => '1989012', 'user_token' => 'live-tok'])
            ->assertNoContent();

        // Бизнес вернулся из маркетплейса залогиненным — материализуем подключение.
        $this->actingAs($owner)->get('/yclients/connect?salon_id=1989012')->assertRedirect();

        $this->assertDatabaseHas('crm_connections', [
            'tenant_id' => $tenant->id,
            'provider' => CrmProvider::Yclients->value,
            'is_active' => true,
        ]);
    }

    public function test_webhook_rejects_wrong_partner_token(): void
    {
        config(['services.yclients.partner_token' => 'correct-partner']);

        $this->postJson('/yclients/webhook', ['salon_id' => '9', 'partner_token' => 'forged'])
            ->assertForbidden();

        $this->assertDatabaseMissing('yclients_links', ['salon_id' => '9']);
    }

    public function test_disconnect_webhook_removes_connection(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->postJson('/yclients/webhook', ['salon_id' => '1989012', 'user_token' => 'live-tok'])->assertNoContent();
        $this->actingAs($owner)->get('/yclients/connect?salon_id=1989012');

        $this->assertDatabaseHas('crm_connections', ['tenant_id' => $tenant->id]);

        $this->postJson('/yclients/disconnect', ['salon_id' => '1989012'])->assertNoContent();

        $this->assertDatabaseMissing('crm_connections', ['tenant_id' => $tenant->id]);
    }
}
