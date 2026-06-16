<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class ConversationJournalTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_conversation_journal(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $conv = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'contact_name' => 'Иван Петров',
            'last_message_at' => now(),
        ]);
        Message::factory()->create(['tenant_id' => $tenant->id, 'conversation_id' => $conv->id, 'direction' => MessageDirection::Inbound, 'text' => 'Здравствуйте, есть фейд?']);
        Message::factory()->create(['tenant_id' => $tenant->id, 'conversation_id' => $conv->id, 'direction' => MessageDirection::Outbound, 'text' => 'Да, конечно! Записать вас?']);

        $this->actingAs($owner)
            ->get('/cabinet/conversations')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cabinet/Conversations/Index')
                ->has('conversations', 1)
                ->where('conversations.0.contact', 'Иван Петров')
                ->where('conversations.0.messagesCount', 2));
    }

    public function test_owner_opens_full_thread(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        $conv = Conversation::factory()->create(['tenant_id' => $tenant->id, 'contact_name' => 'Мария']);
        Message::factory()->create(['tenant_id' => $tenant->id, 'conversation_id' => $conv->id, 'direction' => MessageDirection::Inbound, 'text' => 'Привет']);

        $this->actingAs($owner)
            ->get("/cabinet/conversations/{$conv->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cabinet/Conversations/Show')
                ->where('conversation.contact', 'Мария')
                ->has('messages', 1)
                ->where('messages.0.text', 'Привет'));
    }

    public function test_owner_cannot_open_other_tenant_conversation(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $otherTenant = Tenant::factory()->create();
        $otherConv = Conversation::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->actingAs($owner)->get("/cabinet/conversations/{$otherConv->id}")->assertNotFound();
    }

    public function test_journal_requires_auth(): void
    {
        $this->get('/cabinet/conversations')->assertRedirect('/login');
    }

    public function test_search_filters_by_contact_name(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        Conversation::factory()->create(['tenant_id' => $tenant->id, 'contact_name' => 'Ivan Petrov']);
        Conversation::factory()->create(['tenant_id' => $tenant->id, 'contact_name' => 'Maria Sidorova']);

        // Поиск регистронезависимый (на проде Postgres lower() работает и с кириллицей).
        $this->actingAs($owner)
            ->get('/cabinet/conversations?search=ivan')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('conversations', 1)
                ->where('conversations.0.contact', 'Ivan Petrov'));
    }

    public function test_search_finds_by_message_text(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        $conv = Conversation::factory()->create(['tenant_id' => $tenant->id, 'contact_name' => 'Guest One']);
        Message::factory()->create(['tenant_id' => $tenant->id, 'conversation_id' => $conv->id, 'text' => 'How much is the fade haircut?']);
        Conversation::factory()->create(['tenant_id' => $tenant->id, 'contact_name' => 'Guest Two']);

        $this->actingAs($owner)
            ->get('/cabinet/conversations?search=fade')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('conversations', 1)->where('conversations.0.contact', 'Guest One'));
    }

    public function test_status_filter(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        Conversation::factory()->create(['tenant_id' => $tenant->id, 'status' => ConversationStatus::NeedsHuman]);
        Conversation::factory()->create(['tenant_id' => $tenant->id, 'status' => ConversationStatus::Open]);

        $this->actingAs($owner)
            ->get('/cabinet/conversations?status=needs_human')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('conversations', 1)->where('conversations.0.status', 'needs_human'));
    }

    public function test_grid_shows_and_searches_by_phone(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        Conversation::factory()->create(['tenant_id' => $tenant->id, 'contact_name' => 'Без телефона', 'contact_phone' => null, 'last_message_at' => now()->subHour()]);
        Conversation::factory()->create(['tenant_id' => $tenant->id, 'contact_name' => 'Сергей', 'contact_phone' => '+79991234567', 'last_message_at' => now()]);

        $this->actingAs($owner)
            ->get('/cabinet/conversations')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('conversations.0.phone', '+79991234567'));

        $this->actingAs($owner)
            ->get('/cabinet/conversations?search=9991234567')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('conversations', 1)->where('conversations.0.contact', 'Сергей'));
    }

    public function test_pagination_limits_to_15_per_page(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        Conversation::factory()->count(20)->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)
            ->get('/cabinet/conversations')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('conversations', 15)
                ->where('pagination.total', 20)
                ->where('pagination.last', 2));
    }
}
