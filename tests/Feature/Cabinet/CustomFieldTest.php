<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\DTO\CustomFieldDefData;
use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Enums\UserRole;
use App\Models\CustomFieldDef;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CustomFieldService;
use App\Services\DealService;
use App\Tenancy\TenantInitializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class CustomFieldTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantWithOwner(): array
    {
        $tenant = Tenant::factory()->max()->create();

        return [$tenant, User::factory()->owner($tenant)->create()];
    }

    /**
     * @param  list<string>  $permissions
     */
    private function member(Tenant $tenant, array $permissions): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::Member->value,
            'permissions' => $permissions,
        ]);
    }

    public function test_owner_creates_field_definition(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)
            ->post('/cabinet/custom-fields', ['entity' => 'deal', 'label' => 'Бюджет', 'type' => 'number'])
            ->assertRedirect();

        $this->assertDatabaseHas('custom_field_defs', ['tenant_id' => $tenant->id, 'entity' => 'deal', 'label' => 'Бюджет', 'type' => 'number']);
    }

    public function test_select_field_requires_options(): void
    {
        [, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)
            ->post('/cabinet/custom-fields', ['entity' => 'deal', 'label' => 'Источник', 'type' => 'select', 'options' => []])
            ->assertSessionHasErrors('options');
    }

    public function test_fields_are_exposed_on_deals_index(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $this->makeField($tenant, CustomFieldEntity::Deal, 'Бюджет', CustomFieldType::Number);

        $this->actingAs($owner)
            ->get('/cabinet/deals')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('fields', 1));
    }

    public function test_deal_stores_sanitized_custom_values(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $def = $this->makeField($tenant, CustomFieldEntity::Deal, 'Бюджет', CustomFieldType::Number);
        $stageId = $this->firstStageId($tenant);

        $this->actingAs($owner)->post('/cabinet/deals', [
            'stage_id' => $stageId,
            'title' => 'Сделка',
            'custom' => [$def->key => '50000', 'ghost' => 'x'],
        ])->assertRedirect();

        $deal = Deal::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(50000, $deal->custom[$def->key]);
        $this->assertArrayNotHasKey('ghost', $deal->custom); // неизвестный ключ отброшен
    }

    public function test_member_without_edit_right_cannot_manage_fields(): void
    {
        [$tenant] = $this->tenantWithOwner();

        // Доступ к разделу есть, права-действия deals.edit — нет.
        $this->actingAs($this->member($tenant, ['deals']))
            ->post('/cabinet/custom-fields', ['entity' => 'deal', 'label' => 'X', 'type' => 'text'])
            ->assertForbidden();

        $this->assertDatabaseMissing('custom_field_defs', ['tenant_id' => $tenant->id, 'label' => 'X']);
    }

    public function test_fields_gated_behind_crm_plan(): void
    {
        $tenant = Tenant::factory()->create(); // trial — без crm
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)
            ->post('/cabinet/custom-fields', ['entity' => 'deal', 'label' => 'X', 'type' => 'text'])
            ->assertForbidden();
    }

    public function test_field_of_another_tenant_is_not_accessible(): void
    {
        [, $owner] = $this->tenantWithOwner();
        $other = Tenant::factory()->max()->create();
        $foreign = $this->makeField($other, CustomFieldEntity::Deal, 'Чужое', CustomFieldType::Text);

        $this->actingAs($owner)->delete("/cabinet/custom-fields/{$foreign->id}")->assertNotFound();
    }

    private function makeField(Tenant $tenant, CustomFieldEntity $entity, string $label, CustomFieldType $type): CustomFieldDef
    {
        return $this->app->make(TenantInitializer::class)->run(
            $tenant->id,
            fn () => $this->app->make(CustomFieldService::class)->createDef(new CustomFieldDefData($entity, $label, $type)),
        );
    }

    private function firstStageId(Tenant $tenant): string
    {
        $this->app->make(TenantInitializer::class)->run($tenant->id, fn () => $this->app->make(DealService::class)->ensureStages());

        return (string) DealStage::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('sort_order')->firstOrFail()->id;
    }
}
