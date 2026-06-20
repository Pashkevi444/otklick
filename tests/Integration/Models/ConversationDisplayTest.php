<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\Channel;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ConversationDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_display_prefers_client_card_over_buffer(): void
    {
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Иван', 'phone' => '+79990001122', 'email' => 'i@b.ru']);

        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id, 'channel_id' => $channel->id, 'client_id' => $client->id,
            'contact_name' => 'Старое имя', 'contact_phone' => '+70000000000', 'contact_email' => 'old@b.ru',
        ]);

        // Карточка — источник правды, буфер игнорируется.
        $this->assertSame('Иван', $conversation->displayName());
        $this->assertSame('+79990001122', $conversation->displayPhone());
        $this->assertSame('i@b.ru', $conversation->displayEmail());
    }

    public function test_display_falls_back_to_buffer_without_client(): void
    {
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);

        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id, 'channel_id' => $channel->id, 'client_id' => null,
            'contact_name' => 'Гость Пётр', 'contact_phone' => '+79991112233',
        ]);

        $this->assertSame('Гость Пётр', $conversation->displayName());
        $this->assertSame('+79991112233', $conversation->displayPhone());
    }
}
