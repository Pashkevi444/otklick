<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class AdminSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_site_editor(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get('/admin/site')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Site/Edit')
                ->has('settings.hero_title'));
    }

    public function test_super_admin_updates_site_content(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->put('/admin/site', [
            'hero_title' => 'Новый заголовок',
            'hero_subtitle' => 'Новый подзаголовок',
            'phone' => '88005553535',
            'email' => 'new@otklick.io',
            'telegram' => 'newtg',
            'legal_name' => 'ИП Тестовый Тест Тестович',
            'inn' => '111111111111',
            'ogrnip' => '999999999999999',
            'access_note' => 'Свяжитесь с нами.',
        ])->assertRedirect(route('admin.site.edit'))->assertSessionHas('success');

        $this->assertDatabaseHas('site_settings', [
            'hero_title' => 'Новый заголовок',
            'phone' => '88005553535',
            'legal_name' => 'ИП Тестовый Тест Тестович',
            'inn' => '111111111111',
        ]);
    }

    public function test_tenant_user_cannot_access_site_editor(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->get('/admin/site')->assertForbidden();
    }
}
