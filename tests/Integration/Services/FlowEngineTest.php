<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Flow;
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

    public function test_semantic_match_on_synonym_phrasing(): void
    {
        // Стеммер не ловит синонимы/перефразирование — ловит семантика (эмбеддинги).
        // FakeEmbedder = мешок слов, поэтому порог в тесте занижаем.
        config(['services.flows.semantic_threshold' => 0.3]);

        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]);

        app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowService::class)->create($tenant->id, [
            'name' => 'Сертификаты',
            'is_active' => true,
            'triggers' => ['подарочный сертификат'],
            'definition' => ['start' => 'n1', 'nodes' => [
                'n1' => ['type' => 'message', 'text' => 'Оформим сертификат?', 'action' => 'none', 'options' => [
                    ['label' => 'Да', 'next' => 'n1'],
                ]],
            ]],
        ]));

        // Фраза-триггер не встречается дословно → лексический проход мимо, работает семантика.
        $reply = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'хочу сертификат в подарок'));

        $this->assertNotNull($reply);
        $this->assertSame('Оформим сертификат?', $reply->text);
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
