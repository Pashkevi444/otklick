<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Modules\Bot\Services\BotResponder;
use App\Modules\Clients\Models\Client;
use App\Modules\Conversations\Models\Conversation;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Tenant;
use App\Shared\Models\User;
use App\Shared\Tenancy\TenantInitializer;
use App\Shared\Tenancy\TestContext;
use App\Shared\Vision\Contracts\ImageToText;
use App\Shared\Vision\FakeImageToText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_sandbox_client_phone_does_not_collide_with_real_client(): void
    {
        // Прод-баг: тестер вводил телефон реального клиента, в режиме теста реальные
        // строки не видны → вставка падала на unique(tenant_id, phone). Частичный
        // индекс исключает тестовых клиентов — конфликта быть не должно.
        $tenant = Tenant::factory()->create();

        app(TenantInitializer::class)->run($tenant->id, function () use ($tenant): void {
            Client::factory()->create(['tenant_id' => $tenant->id, 'phone' => '+70001112233', 'is_test' => false]);

            app(TestContext::class)->run(
                fn () => Client::factory()->create(['tenant_id' => $tenant->id, 'phone' => '+70001112233']),
            );

            $this->assertSame(2, Client::query()->withTest()->where('phone', '+70001112233')->count());
            $this->assertDatabaseHas('clients', ['phone' => '+70001112233', 'is_test' => true]);
        });
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

    public function test_image_attachment_runs_in_sandbox_without_pollution(): void
    {
        Storage::fake('public');
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        // Vision выключен (fake) → фото «ушло бы администратору» + пояснение тестеру.
        $this->actingAs($owner)
            ->post('/cabinet/testing/image', ['image' => UploadedFile::fake()->image('cut.jpg', 400, 400)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonStructure(['text', 'buttons', 'escalate', 'booked', 'cancelled', 'note', 'images'])
            ->assertJsonPath('escalate', true);

        // Тестовый прогон не пишет в реальные лиды/клиенты.
        $this->assertSame(0, Conversation::query()->count());
        $this->assertSame(1, Conversation::query()->withTest()->count());
    }

    public function test_recognized_image_is_fed_to_bot_in_sandbox(): void
    {
        Storage::fake('public');
        $this->app->instance(ImageToText::class, new FakeImageToText('каре с чёлкой'));
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        // Сначала проходим согласие на ПД ответом «Да» (ConsentGate — первый рубеж).
        $this->actingAs($owner)->postJson('/cabinet/testing/message', ['text' => 'Да'])->assertOk();

        // Фото распознано (vision) → бот отвечает по описанию, НЕ шлёт администратору.
        $this->actingAs($owner)
            ->post('/cabinet/testing/image', ['image' => UploadedFile::fake()->image('cut.jpg', 400, 400)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('escalate', false);
    }
}
