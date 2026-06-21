<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Главное меню бота: владелец редактирует кнопки; доступ сотруднику выдаётся
 * правом `menu` («Команда»).
 */
final class BotMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_saves_menu_buttons(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->get('/cabinet/menu')->assertOk();

        $this->actingAs($owner)->put('/cabinet/menu', [
            'buttons' => ['Цены и услуги', '  ', 'Адрес'],
        ])->assertRedirect(route('cabinet.menu.edit'));

        // Пустые подписи отброшены, порядок сохранён.
        $this->assertSame(['Цены и услуги', 'Адрес'], $tenant->fresh()->settings['bot_menu']);
    }

    public function test_member_needs_menu_permission(): void
    {
        $tenant = Tenant::factory()->create();

        $allowed = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Member->value, 'permissions' => ['menu'],
        ]);
        $denied = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Member->value, 'permissions' => ['conversations'],
        ]);

        $this->actingAs($allowed)->get('/cabinet/menu')->assertOk();
        $this->actingAs($denied)->get('/cabinet/menu')->assertForbidden();
    }
}
