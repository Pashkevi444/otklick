<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\KnowledgeTemplate;
use App\Models\ScenarioTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Управление глобальными шаблонами (сценарии + база знаний) супер-админом:
 * CRUD, доступ только СУ, начальный набор засеян миграцией.
 */
final class TemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_seeds_templates(): void
    {
        // 20 общих + по 30 на 6 ниш = 200 шаблонов БЗ; сценариев — по ~10+ на нишу.
        $this->assertSame(200, KnowledgeTemplate::count());
        $this->assertSame(20, KnowledgeTemplate::whereNull('business_type')->count());
        $this->assertSame(30, KnowledgeTemplate::where('business_type', 'nails')->count());
        $this->assertGreaterThanOrEqual(10, ScenarioTemplate::where('business_type', 'nails')->count());

        // В шаблонах сценариев нет действия start_booking (YClients может быть выключен).
        $this->assertFalse(
            ScenarioTemplate::get()->contains(fn (ScenarioTemplate $t): bool => str_contains((string) json_encode($t->definition), 'start_booking')),
        );
    }

    public function test_super_admin_sees_template_admin(): void
    {
        $su = User::factory()->superAdmin()->create();

        $this->actingAs($su)->get('/admin/knowledge-templates')->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Admin/KnowledgeTemplates/Index')->has('templates'));

        $this->actingAs($su)->get('/admin/scenario-templates')->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Admin/ScenarioTemplates/Index')->has('templates'));
    }

    public function test_non_super_admin_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->get('/admin/knowledge-templates')->assertForbidden();
        $this->actingAs($owner)->get('/admin/scenario-templates')->assertForbidden();
    }

    public function test_super_admin_crud_knowledge_template(): void
    {
        $su = User::factory()->superAdmin()->create();

        $this->actingAs($su)->post('/admin/knowledge-templates', [
            'key' => 'custom_kb',
            'title' => 'Кастомный шаблон',
            'content' => 'Текст …',
            'business_type' => 'nails',
            'sort_order' => 5,
        ])->assertRedirect();

        $template = KnowledgeTemplate::query()->where('key', 'custom_kb')->firstOrFail();
        $this->assertSame('nails', $template->business_type);

        $this->actingAs($su)->put("/admin/knowledge-templates/{$template->id}", [
            'key' => 'custom_kb',
            'title' => 'Обновлён',
            'content' => 'Новый текст',
            'business_type' => '',
            'sort_order' => 5,
        ])->assertRedirect();

        $this->assertDatabaseHas('knowledge_templates', ['id' => $template->id, 'title' => 'Обновлён', 'business_type' => null]);

        $this->actingAs($su)->delete("/admin/knowledge-templates/{$template->id}")->assertRedirect();
        $this->assertDatabaseMissing('knowledge_templates', ['id' => $template->id]);
    }

    public function test_super_admin_crud_scenario_template(): void
    {
        $su = User::factory()->superAdmin()->create();
        $definition = ['start' => 'n1', 'nodes' => ['n1' => ['type' => 'message', 'action' => 'escalate', 'text' => '…', 'options' => []]]];

        $this->actingAs($su)->post('/admin/scenario-templates', [
            'key' => 'custom_flow',
            'name' => 'Кастомный сценарий',
            'description' => 'Описание',
            'business_type' => 'barbershop',
            'triggers' => ['привет', 'тест'],
            'definition' => $definition,
            'sort_order' => 3,
        ])->assertRedirect();

        $template = ScenarioTemplate::query()->where('key', 'custom_flow')->firstOrFail();
        $this->assertSame(['привет', 'тест'], $template->triggers);
        $this->assertSame('n1', $template->definition['start']);

        $this->actingAs($su)->delete("/admin/scenario-templates/{$template->id}")->assertRedirect();
        $this->assertDatabaseMissing('scenario_templates', ['id' => $template->id]);
    }

    public function test_duplicate_key_rejected(): void
    {
        $su = User::factory()->superAdmin()->create();
        $existing = KnowledgeTemplate::query()->first();

        $this->actingAs($su)->post('/admin/knowledge-templates', [
            'key' => $existing->key,
            'title' => 'Дубль',
            'content' => 'x',
        ])->assertSessionHasErrors('key');
    }
}
