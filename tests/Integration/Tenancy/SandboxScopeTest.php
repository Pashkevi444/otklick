<?php

declare(strict_types=1);

namespace Tests\Integration\Tenancy;

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Tenancy\TenantInitializer;
use App\Tenancy\TestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Изоляция «песочницы»: строки, созданные в тестовом режиме, помечаются в реестре,
 * скрыты из обычных выборок и видны только в режиме теста.
 */
final class SandboxScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_rows_are_registered_hidden_normally_and_visible_in_test_mode(): void
    {
        $tenant = Tenant::factory()->create();
        $tenancy = app(TenantInitializer::class);

        [$real, $test] = $tenancy->run($tenant->id, function () use ($tenant): array {
            $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
            $real = Conversation::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]);

            // Тестовая строка создаётся в активном TestContext.
            $test = app(TestContext::class)->run(
                fn (): Conversation => Conversation::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]),
            );

            return [$real, $test];
        });

        // Тестовый диалог попал в реестр.
        $this->assertDatabaseHas('sandbox_records', [
            'recordable_type' => 'conversations',
            'recordable_id' => $test->id,
            'tenant_id' => $tenant->id,
        ]);

        $tenancy->run($tenant->id, function () use ($real, $test): void {
            // Обычный режим: виден реальный диалог, тестовый скрыт.
            $normal = Conversation::query()->pluck('id');
            $this->assertTrue($normal->contains($real->id));
            $this->assertFalse($normal->contains($test->id));

            // Режим теста: видна ТОЛЬКО песочница (реальный диалог скрыт —
            // пайплайн в тесте не заденет настоящих клиентов/лиды).
            $sandbox = app(TestContext::class)->run(fn () => Conversation::query()->pluck('id'));
            $this->assertTrue($sandbox->contains($test->id));
            $this->assertFalse($sandbox->contains($real->id));
        });
    }
}
