<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class WidgetCabinetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantWithOwner(): array
    {
        $tenant = Tenant::factory()->create();

        return [$tenant, User::factory()->owner($tenant)->create()];
    }

    public function test_index_shows_connect_prompt_when_no_widget(): void
    {
        [, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)
            ->get('/cabinet/widget')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cabinet/Widget/Index')
                ->where('widget', null));
    }

    public function test_owner_connects_widget_and_sees_snippet(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post('/cabinet/widget')->assertRedirect(route('cabinet.widget.index'));

        $this->assertDatabaseHas('channels', ['tenant_id' => $tenant->id, 'type' => ChannelType::Web->value]);

        $this->actingAs($owner)
            ->get('/cabinet/widget')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cabinet/Widget/Index')
                ->has('widget.snippet')
                ->has('widget.scriptUrl'));
    }

    public function test_owner_sets_allowed_origins(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post('/cabinet/widget');
        $channel = Channel::where('tenant_id', $tenant->id)->firstOrFail();

        $this->actingAs($owner)
            ->put("/cabinet/widget/{$channel->id}", ['origins' => "https://shop.ru\nhttps://www.shop.ru"])
            ->assertRedirect(route('cabinet.widget.index'));

        $this->assertSame(['https://shop.ru', 'https://www.shop.ru'], $channel->fresh()->settings['allowed_origins']);
    }

    public function test_owner_sets_widget_color(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post('/cabinet/widget');
        $channel = Channel::where('tenant_id', $tenant->id)->firstOrFail();

        $this->actingAs($owner)
            ->put("/cabinet/widget/{$channel->id}/appearance", ['color' => '#7C3AED'])
            ->assertRedirect(route('cabinet.widget.index'));

        $this->assertSame('#7c3aed', $channel->fresh()->settings['widget_color']);
    }

    public function test_setting_color_keeps_allowed_origins(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post('/cabinet/widget');
        $channel = Channel::where('tenant_id', $tenant->id)->firstOrFail();

        $this->actingAs($owner)->put("/cabinet/widget/{$channel->id}", ['origins' => 'https://shop.ru']);
        $this->actingAs($owner)->put("/cabinet/widget/{$channel->id}/appearance", ['color' => '#10B981']);

        $fresh = $channel->fresh();
        $this->assertSame(['https://shop.ru'], $fresh->settings['allowed_origins']);
        $this->assertSame('#10b981', $fresh->settings['widget_color']);
    }

    public function test_empty_color_resets_to_brand(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post('/cabinet/widget');
        $channel = Channel::where('tenant_id', $tenant->id)->firstOrFail();

        $this->actingAs($owner)->put("/cabinet/widget/{$channel->id}/appearance", ['color' => '#10B981']);
        $this->actingAs($owner)->put("/cabinet/widget/{$channel->id}/appearance", ['color' => '']);

        $this->assertArrayNotHasKey('widget_color', $channel->fresh()->settings);
    }

    public function test_invalid_color_is_rejected(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post('/cabinet/widget');
        $channel = Channel::where('tenant_id', $tenant->id)->firstOrFail();

        $this->actingAs($owner)
            ->put("/cabinet/widget/{$channel->id}/appearance", ['color' => 'red'])
            ->assertSessionHasErrors('color');
    }

    public function test_index_exposes_widget_color(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $this->actingAs($owner)->post('/cabinet/widget');

        $this->actingAs($owner)
            ->get('/cabinet/widget')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cabinet/Widget/Index')
                ->where('widget.color', '#2E74B5'));
    }

    public function test_widget_tab_is_not_available_to_guests(): void
    {
        $this->get('/cabinet/widget')->assertRedirect('/login');
    }
}
