<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Models\BusinessType;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class KnowledgeBaseTest extends TestCase
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

    public function test_index_lists_only_own_entries(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        KnowledgeEntry::factory()->create(['tenant_id' => $tenant->id]);

        $otherTenant = Tenant::factory()->create();
        KnowledgeEntry::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->actingAs($owner)
            ->get('/cabinet/knowledge')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cabinet/KnowledgeBase/Index')
                ->has('entries', 1));
    }

    public function test_index_exposes_kb_templates_for_general_and_tenant_niche_only(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $tenant->update(['business_type' => 'nails']); // ниша тенанта

        $expected = KnowledgeTemplate::query()
            ->where(fn ($q) => $q->whereNull('business_type')->orWhere('business_type', 'nails'))
            ->count();

        $this->actingAs($owner)
            ->get('/cabinet/knowledge')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('templates', $expected)
                ->has('businessTypes', BusinessType::count())
                ->where('templates', fn (Collection $t): bool => $t->every(fn (array $x): bool => array_key_exists('businessType', $x))
                    && $t->contains(fn (array $x): bool => $x['businessType'] === null) // «Общие»
                    && $t->contains(fn (array $x): bool => $x['businessType'] === 'nails') // ниша тенанта
                    && ! $t->contains(fn (array $x): bool => $x['businessType'] === 'barbershop'))); // чужих ниш нет
    }

    public function test_owner_creates_entry(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post('/cabinet/knowledge', [
            'title' => 'Часы работы',
            'content' => 'Ежедневно 9:00–21:00',
            'is_published' => true,
        ])->assertRedirect(route('cabinet.knowledge.index'));

        $this->assertDatabaseHas('knowledge_entries', [
            'tenant_id' => $tenant->id,
            'title' => 'Часы работы',
            'is_published' => true,
        ]);
    }

    public function test_validation_requires_title_and_content(): void
    {
        [, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)
            ->post('/cabinet/knowledge', [])
            ->assertSessionHasErrors(['title', 'content']);
    }

    public function test_owner_updates_own_entry(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $entry = KnowledgeEntry::factory()->create(['tenant_id' => $tenant->id, 'title' => 'Старый']);

        $this->actingAs($owner)->put("/cabinet/knowledge/{$entry->id}", [
            'title' => 'Новый',
            'content' => 'Обновлённый текст',
            'is_published' => false,
        ])->assertRedirect(route('cabinet.knowledge.index'));

        $this->assertDatabaseHas('knowledge_entries', ['id' => $entry->id, 'title' => 'Новый', 'is_published' => false]);
    }

    public function test_owner_deletes_own_entry(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $entry = KnowledgeEntry::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)
            ->delete("/cabinet/knowledge/{$entry->id}")
            ->assertRedirect(route('cabinet.knowledge.index'));

        $this->assertDatabaseMissing('knowledge_entries', ['id' => $entry->id]);
    }

    public function test_owner_cannot_edit_another_tenants_entry(): void
    {
        [, $owner] = $this->tenantWithOwner();
        $otherTenant = Tenant::factory()->create();
        $foreign = KnowledgeEntry::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->actingAs($owner)->get("/cabinet/knowledge/{$foreign->id}/edit")->assertNotFound();
        $this->actingAs($owner)->put("/cabinet/knowledge/{$foreign->id}", [
            'title' => 'x', 'content' => 'y',
        ])->assertNotFound();
    }

    public function test_entry_stores_links_and_uploaded_image(): void
    {
        Storage::fake('public');
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post('/cabinet/knowledge', [
            'title' => 'Стрижка',
            'content' => 'Описание услуги',
            'is_published' => true,
            'links' => [['label' => 'Прайс', 'url' => 'https://example.com/price']],
            'images' => [UploadedFile::fake()->image('work.jpg')],
        ])->assertRedirect(route('cabinet.knowledge.index'));

        $entry = KnowledgeEntry::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertSame([['label' => 'Прайс', 'url' => 'https://example.com/price']], $entry->links);
        $this->assertCount(1, $entry->images);
        Storage::disk('public')->assertExists($entry->images[0]['path']);
    }

    public function test_invalid_link_url_is_rejected(): void
    {
        [, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post('/cabinet/knowledge', [
            'title' => 'T',
            'content' => 'C',
            'links' => [['label' => 'Bad', 'url' => 'not-a-url']],
        ])->assertSessionHasErrors('links.0.url');
    }

    public function test_update_removes_image_from_disk(): void
    {
        Storage::fake('public');
        [$tenant, $owner] = $this->tenantWithOwner();

        $path = UploadedFile::fake()->image('a.jpg')->store("knowledge/{$tenant->id}", 'public');
        $entry = KnowledgeEntry::factory()->create([
            'tenant_id' => $tenant->id,
            'images' => [['path' => $path, 'url' => Storage::disk('public')->url($path)]],
        ]);

        $this->actingAs($owner)->put("/cabinet/knowledge/{$entry->id}", [
            'title' => $entry->title,
            'content' => $entry->content,
            'existing_images' => [],
        ])->assertRedirect(route('cabinet.knowledge.index'));

        Storage::disk('public')->assertMissing($path);
        $this->assertCount(0, $entry->fresh()->images);
    }
}
