<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\BusinessProfile;
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

    public function test_handles_empty_knowledge_base(): void
    {
        $prompt = (new PromptBuilder)->build('Бизнес', new BusinessProfile, new Collection);

        $this->assertStringContainsString('База знаний пуста', $prompt);
    }
}
