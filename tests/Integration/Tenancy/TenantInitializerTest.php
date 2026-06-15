<?php

declare(strict_types=1);

namespace Tests\Integration\Tenancy;

use App\Tenancy\TenantContext;
use App\Tenancy\TenantInitializer;
use RuntimeException;
use Tests\TestCase;

final class TenantInitializerTest extends TestCase
{
    private TenantInitializer $initializer;

    private TenantContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = $this->app->make(TenantContext::class);
        $this->initializer = $this->app->make(TenantInitializer::class);
    }

    public function test_initialize_sets_tenant_in_context(): void
    {
        $this->initializer->initialize('tenant-a');

        $this->assertTrue($this->context->has());
        $this->assertSame('tenant-a', $this->context->id());
    }

    public function test_flush_clears_context(): void
    {
        $this->initializer->initialize('tenant-a');
        $this->initializer->flush();

        $this->assertFalse($this->context->has());
    }

    public function test_run_executes_callback_within_context_and_clears_after(): void
    {
        $seen = $this->initializer->run('tenant-a', fn (): ?string => $this->context->id());

        $this->assertSame('tenant-a', $seen);
        $this->assertFalse($this->context->has());
    }

    public function test_run_clears_context_even_on_exception(): void
    {
        try {
            $this->initializer->run('tenant-a', function (): void {
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
            // ожидаемо
        }

        $this->assertFalse($this->context->has());
    }
}
