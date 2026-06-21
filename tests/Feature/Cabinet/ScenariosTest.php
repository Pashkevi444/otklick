<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Enums\BusinessType;
use App\Models\Flow;
use App\Models\KnowledgeEntry;
use App\Models\ScenarioTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class ScenariosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantWithOwner(): array
    {
        $tenant = Tenant::factory()->max()->create(); // тариф с конструктором сценариев

        return [$tenant, User::factory()->owner($tenant)->create()];
    }

    public function test_index_renders(): void
    {
        [, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)
            ->get('/cabinet/scenarios')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cabinet/Scenarios/Index')
                ->has('flows')
                ->has('actionOptions'));
    }

    public function test_index_exposes_grouped_flow_templates(): void
    {
        [, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)
            ->get('/cabinet/scenarios')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('templates', ScenarioTemplate::count())
                ->has('businessTypes', count(BusinessType::cases()))
                // У каждого шаблона есть тег типа бизнеса (или null — «Общие»).
                ->where('templates', fn (Collection $t): bool => $t->every(fn (array $x): bool => array_key_exists('businessType', $x))
                    && $t->contains(fn (array $x): bool => $x['businessType'] === null) // есть «Общие»
                    && $t->contains(fn (array $x): bool => $x['businessType'] === 'barbershop'))); // и нишевые
    }

    public function test_template_definition_saves_as_valid_flow(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $template = ScenarioTemplate::query()->where('key', 'lead_capture')->firstOrFail();

        $this->actingAs($owner)->post('/cabinet/scenarios', [
            'name' => $template->name,
            'is_active' => true,
            'triggers' => $template->triggers,
            'definition' => $template->definition,
        ])->assertRedirect()->assertSessionHas('success');

        $flow = Flow::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $nodes = $flow->definition['nodes'];
        $this->assertSame('input', $nodes['n1']['type']);
        $this->assertSame('name', $nodes['n1']['variable']);
        $this->assertSame('escalate', $nodes['n3']['action']);
    }

    public function test_index_gates_booking_action_and_exposes_knowledge(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        KnowledgeEntry::factory()->create(['tenant_id' => $tenant->id, 'title' => 'Барбер Никита']);

        // Без подключённого YClients действие start_booking не предлагается,
        // а элементы базы знаний доступны для действия «показать элемент БЗ».
        $this->actingAs($owner)
            ->get('/cabinet/scenarios')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('yclientsActive', false)
                ->where('actionOptions', fn (Collection $opts): bool => $opts->doesntContain(fn (array $o): bool => $o['value'] === 'start_booking')
                    && $opts->contains(fn (array $o): bool => $o['value'] === 'show_knowledge'))
                ->where('knowledgeEntries', fn (Collection $k): bool => $k->contains(fn (array $e): bool => $e['title'] === 'Барбер Никита')));
    }

    public function test_store_preserves_show_knowledge_node_with_images(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $entry = KnowledgeEntry::factory()->create(['tenant_id' => $tenant->id, 'title' => 'Никита']);

        $this->actingAs($owner)->post('/cabinet/scenarios', [
            'name' => 'Барберы',
            'is_active' => true,
            'triggers' => ['барберы'],
            'definition' => ['start' => 'n1', 'nodes' => [
                'n1' => [
                    'type' => 'message', 'text' => 'Вот Никита', 'action' => 'show_knowledge',
                    'knowledge_id' => $entry->id,
                    'images' => [['path' => 'flows/x.jpg', 'url' => 'https://otcl1ck.ru/storage/flows/x.jpg']],
                    'options' => [],
                ],
            ]],
        ])->assertRedirect()->assertSessionHas('success');

        $node = Flow::query()->where('tenant_id', $tenant->id)->firstOrFail()->definition['nodes']['n1'];
        $this->assertSame('show_knowledge', $node['action']);
        $this->assertSame($entry->id, $node['knowledge_id']);
        $this->assertSame('https://otcl1ck.ru/storage/flows/x.jpg', $node['images'][0]['url']);
    }

    public function test_image_upload_returns_path_and_url(): void
    {
        Storage::fake('public');
        [, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)
            ->post('/cabinet/scenarios/image', ['image' => UploadedFile::fake()->image('photo.jpg')])
            ->assertOk()
            ->assertJsonStructure(['path', 'url']);
    }

    public function test_store_creates_flow_for_tenant(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post('/cabinet/scenarios', [
            'name' => 'Акция',
            'is_active' => true,
            'triggers' => ['акция', 'скидка'],
            'definition' => ['start' => 'n1', 'nodes' => ['n1' => ['type' => 'message', 'text' => 'Привет', 'action' => 'none', 'options' => []]]],
        ])->assertRedirect(route('cabinet.scenarios.index'))->assertSessionHas('success');

        $flow = Flow::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('Акция', $flow->name);
        $this->assertTrue($flow->is_active);
        $this->assertSame(['акция', 'скидка'], $flow->triggers);
    }

    public function test_store_preserves_input_and_condition_nodes(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post('/cabinet/scenarios', [
            'name' => 'Анкета',
            'is_active' => true,
            'triggers' => ['анкета'],
            'definition' => ['start' => 'n1', 'nodes' => [
                'n1' => ['type' => 'input', 'text' => 'Как вас зовут?', 'variable' => 'name', 'next' => 'n2'],
                'n2' => ['type' => 'condition', 'variable' => 'name', 'operator' => 'contains', 'value' => 'иван', 'next' => 'n3', 'else' => 'n3'],
                'n3' => ['type' => 'message', 'text' => 'Привет, {{name}}', 'action' => 'end', 'options' => []],
            ]],
        ])->assertRedirect()->assertSessionHas('success');

        $flow = Flow::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $nodes = $flow->definition['nodes'];
        $this->assertSame('input', $nodes['n1']['type']);
        $this->assertSame('name', $nodes['n1']['variable']);
        $this->assertSame('condition', $nodes['n2']['type']);
        $this->assertSame('contains', $nodes['n2']['operator']);
        $this->assertSame('n3', $nodes['n2']['else']);
    }

    public function test_store_preserves_custom_node_title(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post('/cabinet/scenarios', [
            'name' => 'Приветствие',
            'is_active' => true,
            'triggers' => ['привет'],
            'definition' => ['start' => 'n1', 'nodes' => [
                'n1' => ['type' => 'message', 'title' => 'Приветствие клиента', 'text' => 'Здравствуйте!', 'action' => 'end', 'options' => []],
            ]],
        ])->assertRedirect()->assertSessionHas('success');

        $node = Flow::query()->where('tenant_id', $tenant->id)->firstOrFail()->definition['nodes']['n1'];
        $this->assertSame('Приветствие клиента', $node['title']);
    }

    public function test_store_preserves_canvas_positions(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post('/cabinet/scenarios', [
            'name' => 'Схема',
            'is_active' => true,
            'triggers' => ['привет'],
            'definition' => ['start' => 'n1', 'nodes' => [
                'n1' => ['type' => 'message', 'text' => 'Привет', 'action' => 'end', 'options' => [], 'position' => ['x' => 120, 'y' => 40]],
            ]],
        ])->assertRedirect()->assertSessionHas('success');

        $flow = Flow::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(120, $flow->definition['nodes']['n1']['position']['x']);
        $this->assertSame(40, $flow->definition['nodes']['n1']['position']['y']);
    }

    public function test_test_endpoint_runs_dry_simulation(): void
    {
        [, $owner] = $this->tenantWithOwner();
        $definition = ['start' => 'n1', 'nodes' => [
            'n1' => ['type' => 'input', 'text' => 'Как вас зовут?', 'variable' => 'name', 'next' => 'n2'],
            'n2' => ['type' => 'message', 'text' => 'Привет, {{name}}', 'action' => 'end', 'options' => []],
        ]];

        // Старт → узел-вопрос.
        $start = $this->actingAs($owner)->postJson('/cabinet/scenarios/test', ['definition' => $definition, 'state' => null, 'text' => null]);
        $start->assertOk()->assertJsonPath('reply', 'Как вас зовут?')->assertJsonPath('done', false);

        // Ответ → переменная + подстановка, конец.
        $this->actingAs($owner)->postJson('/cabinet/scenarios/test', [
            'definition' => $definition,
            'state' => $start->json(),
            'text' => 'Семён',
        ])->assertOk()->assertJsonPath('reply', 'Привет, Семён')->assertJsonPath('done', true);
    }

    public function test_toggle_flips_active(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $flow = Flow::factory()->for($tenant)->create(['is_active' => false]);

        $this->actingAs($owner)->post("/cabinet/scenarios/{$flow->id}/toggle")->assertRedirect();

        $this->assertTrue($flow->fresh()->is_active);
    }

    public function test_destroy_removes_flow(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $flow = Flow::factory()->for($tenant)->create();

        $this->actingAs($owner)->delete("/cabinet/scenarios/{$flow->id}")->assertRedirect();

        $this->assertDatabaseMissing('flows', ['id' => $flow->id]);
    }

    public function test_gated_off_plan_forbidden(): void
    {
        $tenant = Tenant::factory()->create(); // тариф без flows
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->get('/cabinet/scenarios')->assertForbidden();
    }
}
