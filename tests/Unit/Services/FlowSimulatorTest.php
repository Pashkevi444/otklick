<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\KnowledgeEntry;
use App\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Services\FlowSimulator;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

final class FlowSimulatorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** Симулятор с заглушкой базы знаний (по умолчанию элемент не найден). */
    private function simulator(?KnowledgeEntry $entry = null): FlowSimulator
    {
        $knowledge = Mockery::mock(KnowledgeEntryRepositoryInterface::class);
        $knowledge->shouldReceive('find')->andReturn($entry)->byDefault();

        return new FlowSimulator($knowledge);
    }

    /**
     * @return array<string, mixed>
     */
    private function survey(): array
    {
        return ['start' => 'n1', 'nodes' => [
            'n1' => ['type' => 'input', 'text' => 'Как вас зовут?', 'variable' => 'name', 'next' => 'n2'],
            'n2' => ['type' => 'condition', 'variable' => 'name', 'operator' => 'contains', 'value' => 'иван', 'next' => 'n3', 'else' => 'n4'],
            'n3' => ['type' => 'message', 'text' => 'Привет, {{name}}! Вы Иван.', 'action' => 'end', 'options' => []],
            'n4' => ['type' => 'message', 'text' => 'Здравствуйте, {{name}}.', 'action' => 'end', 'options' => []],
        ]];
    }

    public function test_start_enters_input_node(): void
    {
        $r = ($this->simulator())->step($this->survey(), null, null);

        $this->assertSame('Как вас зовут?', $r['reply']);
        $this->assertSame('n1', $r['node']);
        $this->assertFalse($r['done']);
    }

    public function test_input_stores_var_condition_branches_interpolates(): void
    {
        $sim = $this->simulator();
        $r = $sim->step($this->survey(), ['node' => 'n1', 'vars' => []], 'Иван Петров');

        $this->assertSame('Привет, Иван Петров! Вы Иван.', $r['reply']);
        $this->assertTrue($r['done']);
    }

    public function test_else_branch(): void
    {
        $r = ($this->simulator())->step($this->survey(), ['node' => 'n1', 'vars' => []], 'Пётр');

        $this->assertSame('Здравствуйте, Пётр.', $r['reply']);
    }

    public function test_split_picks_a_variant_with_note(): void
    {
        $def = ['start' => 'n1', 'nodes' => [
            'n1' => ['type' => 'split', 'variants' => [['label' => 'A', 'next' => 'n2'], ['label' => 'B', 'next' => 'n3']]],
            'n2' => ['type' => 'message', 'text' => 'Скидка', 'action' => 'end', 'options' => []],
            'n3' => ['type' => 'message', 'text' => 'Подарок', 'action' => 'end', 'options' => []],
        ]];

        $r = ($this->simulator())->step($def, null, null);

        $this->assertContains($r['reply'], ['Скидка', 'Подарок']);
        $this->assertStringContainsString('A/B: вариант', (string) $r['note']);
    }

    public function test_free_text_not_matching_button_exits(): void
    {
        $def = ['start' => 'n1', 'nodes' => [
            'n1' => ['type' => 'message', 'text' => 'Выберите', 'action' => 'none', 'options' => [['label' => 'Да', 'next' => 'n1']]],
        ]];

        // На узле-кнопках свободный текст не по кнопкам → выход (в боте дальше ИИ).
        $r = ($this->simulator())->step($def, ['node' => 'n1', 'vars' => []], 'абракадабра');

        $this->assertTrue($r['done']);
        $this->assertStringContainsString('не по кнопкам', (string) $r['note']);
    }

    public function test_builtin_client_var_uses_sample_in_test(): void
    {
        $def = ['start' => 'n1', 'nodes' => [
            'n1' => ['type' => 'message', 'text' => 'Здравствуйте, {{client_name}}!', 'action' => 'end', 'options' => []],
        ]];

        $r = ($this->simulator())->step($def, null, null);

        // В тесте client_name — образцовое значение (в боте берётся из карточки).
        $this->assertSame('Здравствуйте, Анна!', $r['reply']);
    }

    public function test_start_booking_action_is_noted_not_executed(): void
    {
        $def = ['start' => 'n1', 'nodes' => [
            'n1' => ['type' => 'message', 'text' => 'Записываю', 'action' => 'start_booking', 'options' => []],
        ]];

        $r = ($this->simulator())->step($def, null, null);

        $this->assertTrue($r['done']);
        $this->assertStringContainsString('YClients', (string) $r['note']);
    }

    public function test_show_knowledge_action_renders_entry_with_links_and_images(): void
    {
        $entry = new KnowledgeEntry([
            'title' => 'Барбер Никита',
            'content' => 'Мастер фейдов.',
            'links' => [['label' => 'Instagram', 'url' => 'https://example.com/n']],
            'images' => [['path' => 'flows/x.jpg', 'url' => 'https://otcl1ck.ru/storage/flows/x.jpg']],
        ]);

        $def = ['start' => 'n1', 'nodes' => [
            'n1' => ['type' => 'message', 'text' => 'Рассказываю:', 'action' => 'show_knowledge', 'knowledge_id' => 'k1', 'options' => []],
        ]];

        $r = $this->simulator($entry)->step($def, null, null);

        $this->assertTrue($r['done']);
        $this->assertStringContainsString('Мастер фейдов', (string) $r['reply']);
        $this->assertStringContainsString('https://example.com/n', (string) $r['reply']);
        $this->assertSame(['https://otcl1ck.ru/storage/flows/x.jpg'], $r['images']);
    }
}
