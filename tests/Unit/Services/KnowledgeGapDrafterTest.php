<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Modules\Bot\Repositories\Contracts\PromptTemplateRepositoryInterface;
use App\Modules\Knowledge\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Modules\Knowledge\Services\KnowledgeGapDrafter;
use App\Shared\Llm\Contracts\LlmClient;
use App\Shared\Models\Tenant;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use RuntimeException;
use Tests\TestCase;

final class KnowledgeGapDrafterTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function drafter(LlmClient $llm): KnowledgeGapDrafter
    {
        $prompts = Mockery::mock(PromptTemplateRepositoryInterface::class);
        $prompts->shouldReceive('behaviorFor')->andReturn('Ты — администратор барбершопа.');

        $knowledge = Mockery::mock(KnowledgeEntryRepositoryInterface::class);
        $knowledge->shouldReceive('publishedForCurrentTenant')->andReturn(new Collection);

        return new KnowledgeGapDrafter($llm, $prompts, $knowledge);
    }

    private function tenant(): Tenant
    {
        return new Tenant(['name' => 'Барбер', 'settings' => []]);
    }

    public function test_drafts_answer_from_llm_and_strips_service_markers(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn('Да, рядом есть парковка. [[PHOTOS]]');

        $this->assertSame(
            'Да, рядом есть парковка.',
            $this->drafter($llm)->draft($this->tenant(), 'Есть парковка рядом?'),
        );
    }

    public function test_empty_question_does_not_call_llm(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldNotReceive('generate');

        $this->assertSame('', $this->drafter($llm)->draft($this->tenant(), '   '));
    }

    public function test_llm_failure_returns_empty_draft(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->andThrow(new RuntimeException('LLM down'));

        $this->assertSame('', $this->drafter($llm)->draft($this->tenant(), 'Вопрос?'));
    }
}
