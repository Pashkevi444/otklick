<?php

declare(strict_types=1);

namespace Tests\Integration\Tenancy;

use App\Modules\Channels\Models\Channel;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Shared\Models\Tenant;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Изоляция тенантов распространяется на messaging-таблицы: один тенант не видит
 * каналы/диалоги/сообщения другого. На sqlite это держит глобальный TenantScope;
 * на PostgreSQL дополнительно — RLS (проверяется отдельно на pgsql).
 */
final class MessagingIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_messaging_tables_are_scoped_to_current_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $context = $this->app->make(TenantContext::class);

        // Данные тенанта A.
        $context->set($tenantA->id);
        $channelA = Channel::factory()->create(['tenant_id' => $tenantA->id]);
        $conversationA = Conversation::factory()->create([
            'tenant_id' => $tenantA->id,
            'channel_id' => $channelA->id,
        ]);
        Message::factory()->create([
            'tenant_id' => $tenantA->id,
            'conversation_id' => $conversationA->id,
        ]);

        // Данные тенанта B.
        $context->set($tenantB->id);
        $channelB = Channel::factory()->create(['tenant_id' => $tenantB->id]);
        Conversation::factory()->create([
            'tenant_id' => $tenantB->id,
            'channel_id' => $channelB->id,
        ]);

        // Под тенантом B видны только его записи.
        $this->assertSame(1, Channel::query()->count());
        $this->assertSame(1, Conversation::query()->count());
        $this->assertSame(0, Message::query()->count());

        // Под тенантом A — только его.
        $context->set($tenantA->id);
        $this->assertSame(1, Channel::query()->count());
        $this->assertSame(1, Conversation::query()->count());
        $this->assertSame(1, Message::query()->count());
    }
}
