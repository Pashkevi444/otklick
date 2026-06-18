<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\BusinessProfile;
use App\Models\CrmKnowledgeEntry;
use App\Models\KnowledgeEntry;
use App\Services\PromptBuilder;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class PromptBuilderTest extends TestCase
{
    public function test_prompt_contains_business_profile_knowledge_and_sentinel(): void
    {
        $entries = new Collection([
            new KnowledgeEntry(['title' => 'Доставка', 'content' => 'Бесплатно от 1000₽', 'links' => []]),
        ]);

        $prompt = (new PromptBuilder)->build(
            'Барбершоп «Бруно»',
            new BusinessProfile(phone: '+7 900', workingHours: 'Пн–Пт 9–20', escalationNote: 'жалобы'),
            $entries,
        );

        $this->assertStringContainsString('Барбершоп «Бруно»', $prompt);
        $this->assertStringContainsString('Телефон: +7 900', $prompt);
        $this->assertStringContainsString('Часы работы: Пн–Пт 9–20', $prompt);
        $this->assertStringContainsString('жалобы', $prompt);
        $this->assertStringContainsString('• Доставка: Бесплатно от 1000₽', $prompt);
        $this->assertStringContainsString(PromptBuilder::ESCALATE, $prompt);
        $this->assertStringContainsString(PromptBuilder::CLARIFY, $prompt);
    }

    public function test_prompt_instructs_proactive_help(): void
    {
        $prompt = (new PromptBuilder)->build('Бизнес', new BusinessProfile, new Collection);

        // Бот должен сам предлагать помощь/варианты, а не только отвечать на прямой вопрос.
        $this->assertStringContainsString('проактивным', $prompt);
        $this->assertStringContainsString('конкретных варианта', $prompt);
    }

    public function test_known_client_is_addressed_by_name_and_not_reasked(): void
    {
        $prompt = (new PromptBuilder)->build('Бизнес', new BusinessProfile, new Collection, false, null, 'Алексей', true);

        $this->assertStringContainsString('Алексей', $prompt);
        $this->assertStringContainsString('УЖЕ знаком', $prompt);
        $this->assertStringContainsString('Повторно имя и телефон НЕ спрашивай', $prompt);
    }

    public function test_unknown_client_has_no_known_contact_block(): void
    {
        $prompt = (new PromptBuilder)->build('Бизнес', new BusinessProfile, new Collection);

        $this->assertStringNotContainsString('УЖЕ знаком', $prompt);
    }

    public function test_prompt_instructs_to_clarify_before_escalating(): void
    {
        $prompt = (new PromptBuilder)->build('Бизнес', new BusinessProfile, new Collection);

        // Непонятный вопрос → сначала уточняем (CLARIFY), а не сразу на человека (ESCALATE).
        $this->assertStringContainsString(PromptBuilder::CLARIFY, $prompt);
        $this->assertStringContainsString('уточняющий вопрос', $prompt);
    }

    public function test_links_are_appended_to_entry(): void
    {
        $entries = new Collection([
            new KnowledgeEntry([
                'title' => 'Прайс',
                'content' => 'Смотрите цены',
                'links' => [['label' => 'Прайс-лист', 'url' => 'https://ex.com/p']],
            ]),
        ]);

        $prompt = (new PromptBuilder)->build('Бизнес', new BusinessProfile, $entries);

        $this->assertStringContainsString('Прайс-лист — https://ex.com/p', $prompt);
    }

    public function test_crm_knowledge_is_included_as_priority_source(): void
    {
        $crm = new Collection([
            new CrmKnowledgeEntry(['category' => 'service', 'title' => 'Стрижка', 'content' => 'Стрижка — 1500 ₽']),
        ]);

        $prompt = (new PromptBuilder)->build('Бизнес', new BusinessProfile, new Collection, false, $crm);

        $this->assertStringContainsString('из системы записи', mb_strtolower($prompt));
        $this->assertStringContainsString('• Стрижка: Стрижка — 1500 ₽', $prompt);
    }

    public function test_handles_empty_knowledge_base(): void
    {
        $prompt = (new PromptBuilder)->build('Бизнес', new BusinessProfile, new Collection);

        $this->assertStringContainsString('База знаний пуста', $prompt);
    }
}
