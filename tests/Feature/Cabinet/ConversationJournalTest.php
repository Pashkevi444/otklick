<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

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
}
