<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Shared\Models\Tenant;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class BusinessProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_renders_current_profile(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Кафе',
            'settings' => ['profile' => ['phone' => '+700', 'working_hours' => '9–18']],
        ]);
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)
            ->get('/cabinet/profile')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cabinet/Profile')
                ->where('profile.name', 'Кафе')
                ->where('profile.phone', '+700'));
    }

    public function test_update_persists_profile(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Кафе']);
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->put('/cabinet/profile', [
            'name' => 'Кафе у дома',
            'phone' => '+712',
            'address' => 'ул. Ленина 1',
            'working_hours' => '10–22',
            'escalation_note' => 'Звонить администратору',
        ])->assertRedirect(route('cabinet.profile.edit'))->assertSessionHas('success');

        $tenant->refresh();
        $this->assertSame('Кафе у дома', $tenant->name);
        $this->assertSame('+712', $tenant->settings['profile']['phone']);
        $this->assertSame('Звонить администратору', $tenant->settings['profile']['escalation_note']);
    }

    public function test_name_is_required(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)
            ->put('/cabinet/profile', ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_update_persists_description_website_and_avatar(): void
    {
        Storage::fake('public');
        $tenant = Tenant::factory()->create(['name' => 'Кафе']);
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->post('/cabinet/profile', [
            '_method' => 'put',
            'name' => 'Кафе у дома',
            'description' => 'Уютное семейное кафе с завтраками',
            'website' => 'cafe.example.ru',
            'avatar' => UploadedFile::fake()->image('logo.jpg', 200, 200),
        ])->assertRedirect(route('cabinet.profile.edit'));

        $tenant->refresh();
        $profile = $tenant->settings['profile'];
        $this->assertSame('Уютное семейное кафе с завтраками', $profile['description']);
        $this->assertSame('cafe.example.ru', $profile['website']);
        $this->assertNotNull($profile['avatar_path']);
        Storage::disk('public')->assertExists($profile['avatar_path']);
    }

    public function test_overview_page_renders_business_card(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Барбершоп Бруно',
            'settings' => ['profile' => ['description' => 'Мужские стрижки', 'phone' => '+7900']],
        ]);
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)
            ->get('/cabinet/overview')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cabinet/Overview')
                ->where('business.name', 'Барбершоп Бруно')
                ->where('business.description', 'Мужские стрижки')
                ->where('business.phone', '+7900'));
    }
}
