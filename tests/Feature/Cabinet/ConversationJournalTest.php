<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class ConversationJournalTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_lead_highlighted_until_dialog_opened(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        $conv = Conversation::factory()->create(['tenant_id' => $tenant->id, 'last_message_at' => now()]);

        $notification = UserNotification::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'type' => 'new_lead',
            'entity_type' => 'conversation',
            'entity_id' => $conv->id,
            'title' => 'Новый лид',
        ]);

        // Список лидов: лид подсвечен «Новый», метку НЕ гасим при открытии списка.
        $this->actingAs($owner)
            ->get('/cabinet/conversations')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('newConversationIds', [$conv->id]));
        $this->assertNull($notification->fresh()->read_at);

        // Открыли диалог → метка гаснет (его уже видели).
        $this->actingAs($owner)->get("/cabinet/conversations/{$conv->id}")->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_read_all_clears_every_new_lead_highlight(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        $a = Conversation::factory()->create(['tenant_id' => $tenant->id, 'last_message_at' => now()]);
        $b = Conversation::factory()->create(['tenant_id' => $tenant->id, 'last_message_at' => now()]);

        foreach ([$a, $b] as $conv) {
            UserNotification::create([
                'tenant_id' => $tenant->id,
                'user_id' => $owner->id,
                'type' => 'new_lead',
                'entity_type' => 'conversation',
                'entity_id' => $conv->id,
                'title' => 'Новый лид',
            ]);
        }

        // Кнопка «Прочитать всё» гасит подсветку у всех лидов разом.
        $this->actingAs($owner)
            ->from('/cabinet/conversations')
            ->post('/cabinet/conversations/read-all')
            ->assertRedirect('/cabinet/conversations');

        $this->actingAs($owner)
            ->get('/cabinet/conversations')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('newConversationIds', []));
    }

    public function test_owner_sees_conversation_journal(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $conv = Conversation::factory()->withClient('Иван Петров')->create([
            'tenant_id' => $tenant->id,
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

    public function test_grid_exposes_conversation_creation_date(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        Conversation::factory()->withClient('Иван Петров')->create([
            'tenant_id' => $tenant->id,
            'created_at' => '2026-01-15 10:30:00',
            'last_message_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get('/cabinet/conversations')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('conversations.0.createdAt', '15.01.2026 10:30'));
    }

    public function test_grid_sorts_by_creation_date(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $old = Conversation::factory()->withClient('Старый')->create([
            'tenant_id' => $tenant->id, 'created_at' => '2026-01-01 09:00:00', 'last_message_at' => now(),
        ]);
        $new = Conversation::factory()->withClient('Новый')->create([
            'tenant_id' => $tenant->id, 'created_at' => '2026-06-01 09:00:00', 'last_message_at' => now()->subDay(),
        ]);

        // По дате создания ↑ — старый первым (порядок не совпадает с last_message_at).
        $this->actingAs($owner)
            ->get('/cabinet/conversations?sort=created&dir=asc')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('conversations.0.contact', 'Старый')
                ->where('conversations.1.contact', 'Новый'));
    }

    public function test_lead_shows_name_and_phone_from_linked_client_card(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        $client = Client::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Алексей Петров',   // актуальное имя в карточке клиента
            'phone' => '+79991112233',
        ]);
        $conv = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'last_message_at' => now(),
        ]);

        // Грид «Лиды» берёт имя/телефон ИЗ карточки клиента.
        $this->actingAs($owner)->get('/cabinet/conversations')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('conversations.0.contact', 'Алексей Петров')
                ->where('conversations.0.phone', '+79991112233'));

        // Карточка лида — тоже из клиента.
        $this->actingAs($owner)->get("/cabinet/conversations/{$conv->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('conversation.contact', 'Алексей Петров')
                ->where('conversation.phone', '+79991112233'));
    }

    public function test_owner_opens_full_thread(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        $conv = Conversation::factory()->withClient('Мария')->create(['tenant_id' => $tenant->id]);
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
        Conversation::factory()->withClient('Ivan Petrov')->create(['tenant_id' => $tenant->id]);
        Conversation::factory()->withClient('Maria Sidorova')->create(['tenant_id' => $tenant->id]);

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
        $conv = Conversation::factory()->withClient('Guest One')->create(['tenant_id' => $tenant->id]);
        Message::factory()->create(['tenant_id' => $tenant->id, 'conversation_id' => $conv->id, 'text' => 'How much is the fade haircut?']);
        Conversation::factory()->withClient('Guest Two')->create(['tenant_id' => $tenant->id]);

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
            ->assertInertia(fn (AssertableInertia $page) => $page->has('conversations', 1)->where('conversations.0.outcome', 'needs_human'));
    }

    public function test_grid_shows_and_searches_by_phone(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        Conversation::factory()->withClient('Без телефона')->create(['tenant_id' => $tenant->id, 'last_message_at' => now()->subHour()]);
        Conversation::factory()->withClient('Сергей', '+79991234567')->create(['tenant_id' => $tenant->id, 'last_message_at' => now()]);

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

    public function test_owner_sets_lead_outcome_and_status_syncs(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        $conv = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => ConversationStatus::Open,
        ]);

        // Любой итог: «потерянный лид» → диалог закрывается.
        $this->actingAs($owner)
            ->put("/cabinet/conversations/{$conv->id}/status", ['outcome' => 'lost'])
            ->assertRedirect();
        $this->assertSame(ConversationStatus::Closed, $conv->fresh()->status);
        $this->assertSame('lost', $conv->fresh()->outcome()->value);

        // «Спам» — тоже закрыт.
        $this->actingAs($owner)
            ->put("/cabinet/conversations/{$conv->id}/status", ['outcome' => 'spam'])
            ->assertRedirect();
        $this->assertSame('spam', $conv->fresh()->outcome()->value);

        // Вернуть «в работу» → статус снова open, итог open (даже если были отметки).
        $this->actingAs($owner)
            ->put("/cabinet/conversations/{$conv->id}/status", ['outcome' => 'open'])
            ->assertRedirect();
        $this->assertSame(ConversationStatus::Open, $conv->fresh()->status);
        $this->assertSame('open', $conv->fresh()->outcome()->value);
    }

    public function test_set_outcome_rejects_unknown_value(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        $conv = Conversation::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)
            ->put("/cabinet/conversations/{$conv->id}/status", ['outcome' => 'bogus'])
            ->assertSessionHasErrors('outcome');
    }

    public function test_owner_cannot_change_status_of_other_tenant_conversation(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        $otherConv = Conversation::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->actingAs($owner)
            ->put("/cabinet/conversations/{$otherConv->id}/status", ['outcome' => 'lost'])
            ->assertNotFound();
    }

    public function test_web_conversation_shows_site_as_source_and_guest_name(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        $channel = Channel::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => ChannelType::Web,
            'settings' => ['allowed_origins' => ['https://shop.example.com']],
        ]);
        Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'last_message_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get('/cabinet/conversations')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('conversations.0.contact', 'Гость')
                ->where('conversations.0.source', 'Веб-виджет · shop.example.com'));
    }
}
