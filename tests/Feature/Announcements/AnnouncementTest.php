<?php

declare(strict_types=1);

namespace Tests\Feature\Announcements;

use App\Enums\AnnouncementType;
use App\Models\Announcement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Анонсы площадки (новости/обновления): публикация супер-админом, лента бизнеса с
 * отметкой прочитанного (пер-тенант), изоляция управления.
 */
final class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function publishedNews(string $title = 'Заголовок'): Announcement
    {
        return Announcement::create([
            'type' => AnnouncementType::News,
            'title' => $title,
            'body' => 'Текст анонса',
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    public function test_super_admin_publishes_and_business_sees_it(): void
    {
        $su = User::factory()->superAdmin()->create();

        $this->actingAs($su)->post('/admin/announcements', [
            'type' => 'news', 'title' => 'Привет', 'body' => 'Тело', 'is_published' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('announcements', ['title' => 'Привет', 'type' => 'news', 'is_published' => true]);

        $owner = User::factory()->owner(Tenant::factory()->create())->create();
        $this->actingAs($owner)->get('/cabinet/news')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Cabinet/Announcements/Index')
                ->has('page.data', 1)
                ->where('page.data.0.is_new', true));
    }

    public function test_business_opens_detail_page(): void
    {
        $announcement = $this->publishedNews('Деталь');
        $owner = User::factory()->owner(Tenant::factory()->create())->create();

        $this->actingAs($owner)->get("/cabinet/news/{$announcement->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Cabinet/Announcements/Show')
                ->where('item.title', 'Деталь'));
    }

    public function test_draft_is_hidden_from_business(): void
    {
        Announcement::create(['type' => AnnouncementType::News, 'title' => 'Черновик', 'body' => 'x', 'is_published' => false]);

        $owner = User::factory()->owner(Tenant::factory()->create())->create();
        $this->actingAs($owner)->get('/cabinet/news')
            ->assertInertia(fn (AssertableInertia $p) => $p->has('page.data', 0));
    }

    public function test_viewing_marks_read_and_is_per_tenant(): void
    {
        $announcement = $this->publishedNews();

        $tenantA = Tenant::factory()->create();
        $ownerA = User::factory()->owner($tenantA)->create();
        $ownerB = User::factory()->owner(Tenant::factory()->create())->create();

        // Тенант A открыл — анонс отмечен прочитанным у него.
        $this->actingAs($ownerA)->get('/cabinet/news')->assertOk();
        $this->assertDatabaseHas('announcement_reads', [
            'announcement_id' => $announcement->id, 'tenant_id' => $tenantA->id,
        ]);

        // Повторно у A — уже не новое.
        $this->actingAs($ownerA)->get('/cabinet/news')
            ->assertInertia(fn (AssertableInertia $p) => $p->where('page.data.0.is_new', false));

        // У тенанта B — всё ещё новое (чтение пер-тенант).
        $this->actingAs($ownerB)->get('/cabinet/news')
            ->assertInertia(fn (AssertableInertia $p) => $p->where('page.data.0.is_new', true));
    }

    public function test_unread_count_shared_to_menu(): void
    {
        $this->publishedNews();
        $owner = User::factory()->owner(Tenant::factory()->create())->create();

        // На любой странице кабинета счётчик непрочитанного доступен меню.
        $this->actingAs($owner)->get('/cabinet')
            ->assertInertia(fn (AssertableInertia $p) => $p->where('announcementsUnread.news', 1));
    }

    public function test_business_cannot_manage_announcements(): void
    {
        $owner = User::factory()->owner(Tenant::factory()->create())->create();

        $this->actingAs($owner)->get('/admin/news')->assertForbidden();
        $this->actingAs($owner)->post('/admin/announcements', [
            'type' => 'news', 'title' => 'x', 'body' => 'y', 'is_published' => true,
        ])->assertForbidden();
    }
}
