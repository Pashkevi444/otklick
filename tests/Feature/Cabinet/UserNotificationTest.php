<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Enums\UserNotificationType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\UserNotificationService;
use App\Tenancy\TenantInitializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class UserNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function notify(Tenant $tenant, UserNotificationType $type, string $title): void
    {
        app(TenantInitializer::class)->run(
            $tenant->id,
            fn () => app(UserNotificationService::class)->notify($type, $title),
        );
    }

    public function test_notify_fans_out_only_to_users_with_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        $withConv = User::factory()->create(['tenant_id' => $tenant->id, 'permissions' => ['conversations']]);
        $withoutConv = User::factory()->create(['tenant_id' => $tenant->id, 'permissions' => ['knowledge']]);

        $this->notify($tenant, UserNotificationType::NewLead, 'Новый лид');

        // Владелец + сотрудник с доступом к диалогам получили; без доступа — нет.
        $this->assertSame(1, UserNotification::withoutGlobalScopes()->where('user_id', $owner->id)->count());
        $this->assertSame(1, UserNotification::withoutGlobalScopes()->where('user_id', $withConv->id)->count());
        $this->assertSame(0, UserNotification::withoutGlobalScopes()->where('user_id', $withoutConv->id)->count());
    }

    public function test_for_user_aggregates_total_and_section_counts(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->notify($tenant, UserNotificationType::NewLead, 'Лид');
        $this->notify($tenant, UserNotificationType::Escalation, 'Эскалация');
        $this->notify($tenant, UserNotificationType::KnowledgeGap, 'Вопрос');
        $this->notify($tenant, UserNotificationType::NewClient, 'Клиент');

        $data = app(TenantInitializer::class)->run(
            $tenant->id,
            fn (): array => app(UserNotificationService::class)->forUser($owner),
        );

        $this->assertSame(4, $data['total']);
        $this->assertSame(2, $data['sections']['conversations']); // лид + эскалация
        $this->assertSame(1, $data['sections']['knowledge']);
        $this->assertSame(1, $data['sections']['clients']);
        $this->assertCount(4, $data['items']);
    }

    public function test_mark_entity_read_clears_only_that_entity_not_whole_section(): void
    {
        // Пер-элемент: открыл ОДИН диалог → гаснет только он, остальные остаются.
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        $convA = (string) Str::uuid();
        $convB = (string) Str::uuid();

        app(TenantInitializer::class)->run($tenant->id, function () use ($convA, $convB): void {
            $svc = app(UserNotificationService::class);
            $svc->notify(UserNotificationType::NewLead, 'Лид А', null, null, 'conversation', $convA);
            $svc->notify(UserNotificationType::NewLead, 'Лид Б', null, null, 'conversation', $convB);
        });

        app(TenantInitializer::class)->run(
            $tenant->id,
            fn () => app(UserNotificationService::class)->markEntityRead($owner, 'conversation', $convA),
        );

        $data = app(TenantInitializer::class)->run(
            $tenant->id,
            fn (): array => app(UserNotificationService::class)->forUser($owner),
        );

        // Погас только диалог А; Б остался непрочитанным — заход в список НЕ гасит всё.
        $this->assertSame(1, $data['total']);
        $this->assertSame(1, $data['sections']['conversations']);
    }

    public function test_feed_endpoint_returns_json_for_cabinet_user(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->notify($tenant, UserNotificationType::NewLead, 'Лид');

        $this->actingAs($owner)
            ->getJson('/cabinet/notifications/feed')
            ->assertOk()
            ->assertJsonStructure(['total', 'sections', 'items']);
    }

    public function test_read_all_marks_everything_read(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->notify($tenant, UserNotificationType::NewLead, 'Лид');
        $this->notify($tenant, UserNotificationType::KnowledgeGap, 'Вопрос');

        $this->actingAs($owner)->postJson('/cabinet/notifications/read')->assertOk();

        $data = app(TenantInitializer::class)->run(
            $tenant->id,
            fn (): array => app(UserNotificationService::class)->forUser($owner),
        );

        $this->assertSame(0, $data['total']);
    }
}
