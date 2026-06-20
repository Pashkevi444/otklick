<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BotResponder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Тестирование бота: доступ (все тарифы + право сотрудника) и изоляция песочницы
 * (тестовые прогоны не попадают в лиды/базу клиентов, чистятся командой).
 */
final class BotTestingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_on_any_plan_can_open_testing(): void
    {
        // Триал — самый младший тариф: тестирование доступно и на нём.
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->get('/cabinet/testing')->assertOk();
    }

    public function test_member_needs_testing_permission(): void
    {
        $tenant = Tenant::factory()->create();

        $allowed = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Member->value, 'permissions' => ['testing'],
        ]);
        $denied = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Member->value, 'permissions' => ['conversations'],
        ]);

        $this->actingAs($allowed)->get('/cabinet/testing')->assertOk();
        $this->actingAs($denied)->get('/cabinet/testing')->assertForbidden();
    }

    public function test_message_replies_without_polluting_leads_or_clients(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)
            ->postJson('/cabinet/testing/message', ['text' => 'Здравствуйте'])
            ->assertOk()
            ->assertJsonStructure(['text', 'buttons', 'escalate', 'booked', 'cancelled', 'note']);

        // Лиды и база клиентов — пусты (тестовый прогон туда не пишет).
        $this->assertSame(0, Conversation::query()->count());
        $this->assertSame(0, Client::query()->count());

        // Тестовый диалог существует — но только как «песочница».
        $this->assertSame(1, Conversation::query()->withTest()->count());
    }

    public function test_pipeline_failure_does_not_500_the_chat(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        // Реальный пайплайн (LLM/эмбеддер/CRM) может бросить — тестовый чат должен
        // вернуть понятный ответ, а не 500.
        $this->mock(BotResponder::class, function ($mock): void {
            $mock->shouldReceive('respond')->andThrow(new RuntimeException('boom'));
        });

        $this->actingAs($owner)
            ->postJson('/cabinet/testing/message', ['text' => 'привет'])
            ->assertOk()
            ->assertJsonPath('text', 'Бот не смог обработать сообщение — что-то пошло не так на стороне сервиса. Мы записали детали для разбора.');
    }

    public function test_purge_command_clears_sandbox(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->postJson('/cabinet/testing/message', ['text' => 'Здравствуйте'])->assertOk();
        $this->assertSame(1, Conversation::query()->withTest()->count());

        $this->artisan('sandbox:purge')->assertSuccessful();

        $this->assertSame(0, Conversation::query()->withTest()->count());
        $this->assertDatabaseCount('sandbox_records', 0);
    }
}
