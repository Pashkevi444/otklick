<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\LeadAnalyticsPeriod;
use App\Llm\Contracts\LlmClient;
use App\Repositories\Contracts\LeadAnalyticsRepositoryInterface;
use App\Services\LeadAnalyticsService;
use App\Services\LeadInsightsService;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

final class LeadInsightsServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function service(LlmClient $llm): LeadInsightsService
    {
        $repo = Mockery::mock(LeadAnalyticsRepositoryInterface::class);
        $repo->shouldReceive('leadsForAnalytics')->andReturn(new Collection);
        $repo->shouldReceive('recentLeads')->andReturn(new Collection);
        $repo->shouldReceive('connectedChannelTypes')->andReturn(['telegram', 'web']);

        $tenant = new TenantContext;
        $tenant->set('t-1');

        return new LeadInsightsService(
            new LeadAnalyticsService($repo),
            $llm,
            $tenant,
            $this->app->make(CacheRepository::class),
        );
    }

    public function test_uses_ai_output_when_valid_json(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn(
            '[{"severity":"high","title":"Мало записей","detail":"конверсия низкая","action":"включите автозапись"}]',
        );

        $result = $this->service($llm)->refresh(LeadAnalyticsPeriod::Month);

        $this->assertSame('ai', $result['source']);
        $this->assertSame('Мало записей', $result['items'][0]['title']);
    }

    public function test_falls_back_to_rules_when_llm_not_json(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn('Извините, не понял запрос.');

        $result = $this->service($llm)->refresh(LeadAnalyticsPeriod::Month);

        $this->assertSame('rules', $result['source']);
        $this->assertNotEmpty($result['items']);
    }

    public function test_caches_result_for_period(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn('[{"title":"X","detail":"d","action":"a"}]');

        $service = $this->service($llm);
        $this->assertNull($service->cached(LeadAnalyticsPeriod::Month));

        $service->refresh(LeadAnalyticsPeriod::Month);

        $this->assertNotNull($service->cached(LeadAnalyticsPeriod::Month));
    }
}
