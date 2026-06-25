<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\DTO\BusinessProfile;
use App\Models\PromptTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\PromptTemplateRepositoryInterface;
use App\Services\PromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class PromptTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_seeds_prompt_per_niche_with_default(): void
    {
        // Универсальный дефолт (NULL business_type) + промпт на каждый business_type.
        $this->assertSame(1, PromptTemplate::whereNull('business_type')->count());
        $this->assertTrue(PromptTemplate::where('business_type', 'barbershop')->exists());

        $body = (string) PromptTemplate::where('business_type', 'barbershop')->value('body');
        $this->assertStringContainsString('{{business_name}}', $body);
        $this->assertStringContainsString('{{photos_marker}}', $body);
        // Хвост (сентинелы записи/эскалации) в БД НЕ хранится — он в коде.
        $this->assertStringNotContainsString('[[ESCALATE]]', $body);
    }

    public function test_super_admin_sees_prompt_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get('/admin/prompt-templates')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Admin/PromptTemplates/Index')
                ->has('templates.data')
                ->has('variables'));
    }

    public function test_non_super_admin_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->get('/admin/prompt-templates')->assertForbidden();
    }

    public function test_super_admin_updates_prompt_body(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $template = PromptTemplate::where('business_type', 'barbershop')->firstOrFail();

        $this->actingAs($admin)
            ->put("/admin/prompt-templates/{$template->id}", [
                'name' => 'Барбершоп (тест)',
                'body' => 'Ты администратор «{{business_name}}». Метка: {{photos_marker}}.',
            ])
            ->assertRedirect();

        $template->refresh();
        $this->assertSame('Барбершоп (тест)', $template->name);
        $this->assertStringContainsString('{{business_name}}', $template->body);
    }

    public function test_behavior_resolved_by_business_type_with_fallback(): void
    {
        $repo = app(PromptTemplateRepositoryInterface::class);

        // Ниша со своим промптом.
        $this->assertNotNull($repo->behaviorFor('barbershop'));
        $this->assertStringContainsString('барбер', mb_strtolower((string) $repo->behaviorFor('barbershop')));

        // Неизвестная ниша → фолбэк на универсальный дефолт (NULL).
        $default = $repo->behaviorFor(null);
        $this->assertNotNull($default);
        $this->assertSame($default, $repo->behaviorFor('no-such-niche'));
    }

    public function test_prompt_builder_substitutes_variables_in_custom_behavior(): void
    {
        $prompt = (new PromptBuilder)->build(
            'Барбер Никита',
            BusinessProfile::fromArray([]),
            collect(),
            behaviorTemplate: 'Ты администратор «{{business_name}}». Если просят фото — добавь {{photos_marker}}.',
        );

        $this->assertStringContainsString('Барбер Никита', $prompt);
        $this->assertStringContainsString('[[PHOTOS]]', $prompt);
        $this->assertStringNotContainsString('{{business_name}}', $prompt);
        // Стандартный хвост на месте (правило эскалации).
        $this->assertStringContainsString('[[ESCALATE]]', $prompt);
    }
}
