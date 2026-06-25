<?php

declare(strict_types=1);

namespace Tests\Integration\Tenancy;

use App\Http\Middleware\BindTenantToRequest;
use App\Shared\Models\Tenant;
use App\Shared\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class TenantBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_user_request_runs_in_tenant_context(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->owner($tenant)->create();

        $context = $this->app->make(TenantContext::class);
        $middleware = $this->app->make(BindTenantToRequest::class);

        $request = Request::create('/cabinet');
        $request->setUserResolver(fn () => $user);

        $seen = null;
        $response = $middleware->handle($request, function () use ($context, &$seen): Response {
            $seen = $context->id();

            return new Response;
        });

        $this->assertSame($tenant->id, $seen);

        $middleware->terminate($request, $response);
        $this->assertFalse($context->has());
    }

    public function test_super_admin_request_has_no_tenant_context(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $context = $this->app->make(TenantContext::class);
        $middleware = $this->app->make(BindTenantToRequest::class);

        $request = Request::create('/admin/tenants');
        $request->setUserResolver(fn () => $admin);

        $seen = 'unset';
        $middleware->handle($request, function () use ($context, &$seen): Response {
            $seen = $context->id();

            return new Response;
        });

        $this->assertNull($seen);
    }
}
