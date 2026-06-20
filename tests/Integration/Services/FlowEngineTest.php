<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Llm\Contracts\Embedder;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Flow;
use App\Models\FlowAbAssignment;
use App\Models\Tenant;
use App\Services\FlowEngine;
use App\Services\FlowService;
use App\Tenancy\TenantInitializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FlowEngineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Tenant, 1: Conversation}
     */
    private function setupFlow(): array
    {
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]);

        Flow::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'triggers' => ['акция'],
            'definition' => [
                'start' => 'n1',
                'nodes' => [
                    'n1' => ['type' => 'message', 'text' => 'Чем помочь?', 'action' => 'none', 'options' => [
                        ['label' => 'Вопрос', 'next' => 'n2'],
                    ]],
                    'n2' => ['type' => 'message', 'text' => 'Передаю администратору.', 'action' => 'escalate', 'options' => []],
                ],
            ],
        ]);

        return [$tenant, $conversation];
    }

    public function test_trigger_starts_flow_and_shows_buttons(): void
    {
        [$tenant, $conversation] = $this->setupFlow();

        $reply = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'есть акция?'));

        $this->assertNotNull($reply);
        $this->assertSame('Чем помочь?', $reply->text);
        $this->assertSame(['Вопрос'], $reply->keyboard?->labels());
        // Диалог встал в воронку (ждёт выбор).
        $this->assertSame('n1', Conversation::withoutGlobalScopes()->findOrFail($conversation->id)->flow_state['node_id'] ?? null);
    }

    public function test_trigger_matches_word_form(): void
    {
        // Прод-баг: триггер «акция» не ловил «есть акции?» (другая словоформа).
        [$tenant, $conversation] = $this->setupFlow();

        $reply = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'а у вас есть акции которые можете предложить?'));

        $this->assertNotNull($reply);
        $this->assertSame('Чем помочь?', $reply->text);
    }

    public function test_multiword_trigger_matches_any_keyword_in_long_message(): void
    {
        // Прод-баг: триггер-фраза «акции скидка» не ловила «…какие нибудь акции
        // есть типо отец и сын?» (фраза не встречается дословно). Многословный
        // триггер = набор ключевых слов: совпало любое слово по основе → срабатываем.
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]);

        app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowService::class)->create($tenant->id, [
            'name' => 'Акции',
            'is_active' => true,
            'triggers' => ['акции скидка'],
            'definition' => ['start' => 'n1', 'nodes' => [
                'n1' => ['type' => 'message', 'text' => 'Вот наши акции', 'action' => 'end', 'options' => []],
            ]],
        ]));

        $reply = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'Хорошо классно а у вас какие нибудь акции есть типо отец и сын?'));

        $this->assertNotNull($reply);
        $this->assertSame('Вот наши акции', $reply->text);
    }

    public function test_semantic_match_when_no_keyword_overlap(): void
    {
        // Семантика ловит перефразирование без общих слов. Эмбеддер-заглушка
        // даёт одинаковый вектор → косинус 1.0 (изолируем именно семантику).
        $this->app->bind(Embedder::class, fn (): Embedder => new class implements Embedder
        {
            public function embed(string $text): array
            {
                return array_fill(0, 8, 1.0);
            }

            public function dimension(): int
            {
                return 8;
            }
        });

        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]);

        app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowService::class)->create($tenant->id, [
            'name' => 'Распродажа',
            'is_active' => true,
            'triggers' => ['распродажа'],
            'definition' => ['start' => 'n1', 'nodes' => [
                'n1' => ['type' => 'message', 'text' => 'Есть выгодные предложения', 'action' => 'end', 'options' => []],
            ]],
        ]));

        // Ни одного общего слова с триггером → лексика мимо, срабатывает семантика.
        $reply = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'хочу что-нибудь подешевле'));

        $this->assertNotNull($reply);
        $this->assertSame('Есть выгодные предложения', $reply->text);
    }

    public function test_input_stores_variable_condition_branches_and_interpolates(): void
    {
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]);

        app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowService::class)->create($tenant->id, [
            'name' => 'Анкета',
            'is_active' => true,
            'triggers' => ['анкета'],
            'definition' => ['start' => 'n1', 'nodes' => [
                'n1' => ['type' => 'input', 'text' => 'Как вас зовут?', 'variable' => 'name', 'next' => 'n2'],
                'n2' => ['type' => 'condition', 'variable' => 'name', 'operator' => 'contains', 'value' => 'иван', 'next' => 'n3', 'else' => 'n4'],
                'n3' => ['type' => 'message', 'text' => 'Привет, {{name}}! Вы Иван.', 'action' => 'end', 'options' => []],
                'n4' => ['type' => 'message', 'text' => 'Здравствуйте, {{name}}.', 'action' => 'end', 'options' => []],
            ]],
        ]));

        // Запуск → узел-вопрос.
        $start = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'хочу анкету'));
        $this->assertSame('Как вас зовут?', $start->text);

        // Ответ → переменная сохранена, условие contains «иван» → ветка «да» + подстановка.
        $reply = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'Иван Петров'));
        $this->assertSame('Привет, Иван Петров! Вы Иван.', $reply->text);
        $this->assertNull(Conversation::withoutGlobalScopes()->findOrFail($conversation->id)->flow_state);
    }

    public function test_condition_else_branch(): void
    {
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]);

        app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowService::class)->create($tenant->id, [
            'name' => 'Анкета',
            'is_active' => true,
            'triggers' => ['анкета'],
            'definition' => ['start' => 'n1', 'nodes' => [
                'n1' => ['type' => 'input', 'text' => 'Как вас зовут?', 'variable' => 'name', 'next' => 'n2'],
                'n2' => ['type' => 'condition', 'variable' => 'name', 'operator' => 'contains', 'value' => 'иван', 'next' => 'n3', 'else' => 'n4'],
                'n3' => ['type' => 'message', 'text' => 'Вы Иван.', 'action' => 'end', 'options' => []],
                'n4' => ['type' => 'message', 'text' => 'Здравствуйте, {{name}}.', 'action' => 'end', 'options' => []],
            ]],
        ]));

        app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'анкета'));
        $reply = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'Пётр'));

        $this->assertSame('Здравствуйте, Пётр.', $reply->text);
    }

    public function test_ab_split_assigns_variant_and_is_sticky(): void
    {
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]);

        app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowService::class)->create($tenant->id, [
            'name' => 'Промо',
            'is_active' => true,
            'triggers' => ['промо'],
            'definition' => ['start' => 'n1', 'nodes' => [
                'n1' => ['type' => 'split', 'variants' => [['label' => 'A', 'next' => 'n2'], ['label' => 'B', 'next' => 'n3']]],
                'n2' => ['type' => 'message', 'text' => 'Вариант А', 'action' => 'end', 'options' => []],
                'n3' => ['type' => 'message', 'text' => 'Вариант Б', 'action' => 'end', 'options' => []],
            ]],
        ]));

        $first = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'промо'));
        $this->assertContains($first->text, ['Вариант А', 'Вариант Б']);
        $this->assertSame(1, FlowAbAssignment::withoutGlobalScopes()->where('conversation_id', $conversation->id)->count());

        // Липко: повторный заход того же диалога → тот же вариант, без нового назначения.
        $second = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'промо'));
        $this->assertSame($first->text, $second->text);
        $this->assertSame(1, FlowAbAssignment::withoutGlobalScopes()->where('conversation_id', $conversation->id)->count());
    }

    public function test_button_choice_advances_and_escalates(): void
    {
        [$tenant, $conversation] = $this->setupFlow();
        $conversation->flow_state = ['flow_id' => Flow::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail()->id, 'node_id' => 'n1'];
        $conversation->save();

        $reply = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'Вопрос'));

        $this->assertNotNull($reply);
        $this->assertTrue($reply->escalate);
        $this->assertSame('Передаю администратору.', $reply->text);
        // Воронка завершена — состояние очищено.
        $this->assertNull(Conversation::withoutGlobalScopes()->findOrFail($conversation->id)->flow_state);
    }

    public function test_free_text_exits_flow_to_ai(): void
    {
        [$tenant, $conversation] = $this->setupFlow();
        $conversation->flow_state = ['flow_id' => Flow::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail()->id, 'node_id' => 'n1'];
        $conversation->save();

        $reply = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'а сколько стоит?'));

        // Не по кнопкам → выходим из воронки (null = дальше отвечает ИИ).
        $this->assertNull($reply);
        $this->assertNull(Conversation::withoutGlobalScopes()->findOrFail($conversation->id)->flow_state);
    }

    public function test_no_trigger_returns_null(): void
    {
        [$tenant, $conversation] = $this->setupFlow();

        $reply = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'привет'));

        $this->assertNull($reply);
    }
}
