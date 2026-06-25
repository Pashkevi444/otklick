<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Modules\Channels\Models\Channel;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Flows\Models\Flow;
use App\Modules\Flows\Models\FlowAbAssignment;
use App\Modules\Flows\Services\FlowEngine;
use App\Modules\Flows\Services\FlowService;
use App\Modules\Knowledge\Models\KnowledgeEntry;
use App\Shared\Llm\Contracts\Embedder;
use App\Shared\Models\Tenant;
use App\Shared\Tenancy\TenantInitializer;
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

    public function test_multiword_trigger_requires_contiguous_phrase(): void
    {
        // Многословный триггер = ОДНА ключевая фраза (а не набор слов): срабатывает,
        // только если фраза встречается целиком (с допуском на опечатки/регистр).
        // Одно случайное слово из фразы больше не запускает воронку.
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

        // Только одно слово «акции» в неродственной фразе → НЕ запускает.
        $miss = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'а у вас какие нибудь акции есть типо отец и сын?'));
        $this->assertNull($miss);

        // Фраза целиком → запускает.
        $hit = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'а есть акции скидка сейчас?'));
        $this->assertNotNull($hit);
        $this->assertSame('Вот наши акции', $hit->text);
    }

    public function test_short_function_word_in_trigger_does_not_match_unrelated_message(): void
    {
        // Прод-баг (Метропольский и Друзья): кнопка «Удлиненные стрижки» запускала
        // воронку «расположение» — служебное слово «к» из триггера «как к вам
        // попасть» находилось ПОДСТРОКОЙ в «стри[ж]ки». Любое сообщение с буквой
        // «к» матчило адресную воронку. Служебные слова ключами быть не должны.
        $this->bindOrthogonalEmbedder(); // изолируем лексику (семантика не вмешивается)

        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]);

        app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowService::class)->create($tenant->id, [
            'name' => 'Расположение',
            'is_active' => true,
            'triggers' => ['как к вам попасть', 'где вы находитесь'],
            'definition' => ['start' => 'n1', 'nodes' => [
                'n1' => ['type' => 'message', 'text' => 'Мы на Красном проспекте', 'action' => 'end', 'options' => []],
            ]],
        ]));

        // «удлиненные стрижки» не про адрес → воронка НЕ должна стартовать.
        $miss = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'Удлиненные стрижки'));
        $this->assertNull($miss);

        // А реальный вопрос про дорогу ловится по содержательному слову «попасть».
        $hit = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'как к вам попасть?'));
        $this->assertNotNull($hit);
        $this->assertSame('Мы на Красном проспекте', $hit->text);
    }

    public function test_menu_click_strict_match_does_not_fire_flow_by_morphology(): void
    {
        // Прод-баг (Метропольский и Друзья): кнопка меню «Типы стрижек» запускала
        // флоу «Барберы» с триггером «стрижка» — по общей основе «стрижк». Клик по
        // кнопке меню = точный интент: флоу запускаем только при точном совпадении.
        $this->bindOrthogonalEmbedder();

        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]);

        app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowService::class)->create($tenant->id, [
            'name' => 'Барберы',
            'is_active' => true,
            'triggers' => ['барберы', 'мастера', 'стрижка'],
            'definition' => ['start' => 'n1', 'nodes' => [
                'n1' => ['type' => 'message', 'text' => 'У нас работают: Никита, Савелий, Кирилл', 'action' => 'end', 'options' => []],
            ]],
        ]));

        // Строгий режим (клик меню): «Типы стрижек» НЕ должен запускать «Барберы».
        $miss = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'Типы стрижек', true));
        $this->assertNull($miss);

        // А кнопка «Барберы» (точное совпадение) — запускает флоу.
        $hit = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'Барберы', true));
        $this->assertNotNull($hit);
        $this->assertStringContainsString('Никита', $hit->text);

        // Без строгого режима (свободный ввод) морфология работает как прежде.
        $fuzzy = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'хочу стрижку'));
        $this->assertNotNull($fuzzy);
    }

    /** Эмбеддер-заглушка: ортогональные векторы по тексту (косинус ≈ 0 для разных строк). */
    private function bindOrthogonalEmbedder(): void
    {
        $this->app->bind(Embedder::class, fn (): Embedder => new class implements Embedder
        {
            public function embed(string $text): array
            {
                $v = array_fill(0, 4096, 0.0);
                $v[abs(crc32($text)) % 4096] = 1.0;

                return $v;
            }

            public function dimension(): int
            {
                return 4096;
            }
        });
    }

    public function test_only_key_phrase_fires_typo_and_case_ok_unrelated_ignored(): void
    {
        // Сценарии запускаются ТОЛЬКО по ключевой фразе (допуск — опечатка/регистр),
        // без семантики. Прод-баг (скрин): «малет стрижка есть примеры?» запускал
        // «визит» через эмбеддинги — теперь не запускает.
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]);

        app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowService::class)->create($tenant->id, [
            'name' => 'Визит',
            'is_active' => true,
            'triggers' => ['посещение', 'визит', 'как проходит'],
            'definition' => ['start' => 'n1', 'nodes' => [
                'n1' => ['type' => 'message', 'text' => 'Визит проходит так…', 'action' => 'end', 'options' => []],
            ]],
        ]));

        // Семантически близко, но фразы-триггера нет → НЕ запускаем.
        $miss = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'а малет стрижка есть примеры?'));
        $this->assertNull($miss);

        // Опечатка в ключевом слове «посещение» → ловим (action=end сразу очищает state).
        $typo = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'расскажите про посещенние салона'));
        $this->assertNotNull($typo);

        // Регистр не важен.
        $caps = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'ВИЗИТ'));
        $this->assertNotNull($caps);
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

    public function test_builtin_client_var_interpolates_from_card(): void
    {
        // {{client_name}} берётся из карточки клиента без переспроса.
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->withClient('Зоя')->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]);

        app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowService::class)->create($tenant->id, [
            'name' => 'Привет',
            'is_active' => true,
            'triggers' => ['привет'],
            'definition' => ['start' => 'n1', 'nodes' => [
                'n1' => ['type' => 'message', 'text' => 'Здравствуйте, {{client_name}}!', 'action' => 'end', 'options' => []],
            ]],
        ]));

        $reply = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'привет'));

        $this->assertSame('Здравствуйте, Зоя!', $reply->text);
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

    public function test_show_knowledge_action_sends_entry_with_links_and_images_and_ends(): void
    {
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]);

        $entry = KnowledgeEntry::factory()->create([
            'tenant_id' => $tenant->id,
            'title' => 'Барбер Никита',
            'content' => 'Никита — мастер фейдов, стаж 5 лет.',
            'is_published' => true,
            'links' => [['label' => 'Instagram', 'url' => 'https://example.com/nikita']],
            'images' => [['path' => 'flows/x.jpg', 'url' => 'https://otcl1ck.ru/storage/flows/x.jpg']],
        ]);

        app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowService::class)->create($tenant->id, [
            'name' => 'Барберы',
            'is_active' => true,
            'triggers' => ['барберы'],
            'definition' => ['start' => 'n1', 'nodes' => [
                'n1' => ['type' => 'message', 'text' => 'Кто интересует?', 'action' => 'none', 'options' => [
                    ['label' => 'Никита', 'next' => 'n2'],
                ]],
                // Кнопка ведёт на узел-действие: показать элемент базы знаний и завершить.
                'n2' => ['type' => 'message', 'text' => 'Рассказываю:', 'action' => 'show_knowledge', 'knowledge_id' => $entry->id, 'options' => []],
            ]],
        ]));

        $conversation->flow_state = ['flow_id' => Flow::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail()->id, 'node_id' => 'n1'];
        $conversation->save();

        $reply = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'Никита'));

        $this->assertNotNull($reply);
        $this->assertFalse($reply->escalate);
        $this->assertStringContainsString('мастер фейдов', $reply->text);
        $this->assertStringContainsString('Рассказываю:', $reply->text);
        $this->assertStringContainsString('https://example.com/nikita', $reply->text);
        $this->assertSame(['https://otcl1ck.ru/storage/flows/x.jpg'], $reply->images);
        // Сценарий завершён.
        $this->assertNull(Conversation::withoutGlobalScopes()->findOrFail($conversation->id)->flow_state);
    }

    public function test_node_images_are_attached_to_reply(): void
    {
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]);

        app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowService::class)->create($tenant->id, [
            'name' => 'Афиша',
            'is_active' => true,
            'triggers' => ['афиша'],
            'definition' => ['start' => 'n1', 'nodes' => [
                'n1' => ['type' => 'message', 'text' => 'Вот наша афиша', 'action' => 'end', 'options' => [],
                    'images' => [['path' => 'flows/a.jpg', 'url' => 'https://otcl1ck.ru/storage/flows/a.jpg']]],
            ]],
        ]));

        $reply = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'афиша'));

        $this->assertNotNull($reply);
        $this->assertSame(['https://otcl1ck.ru/storage/flows/a.jpg'], $reply->images);
    }

    public function test_no_trigger_returns_null(): void
    {
        [$tenant, $conversation] = $this->setupFlow();

        $reply = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowEngine::class)->handle($tenant, $conversation, 'привет'));

        $this->assertNull($reply);
    }
}
