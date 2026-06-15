<?php

declare(strict_types=1);

namespace Tests\Unit\Crm;

use App\Crm\CrmGatewayResolver;
use App\Crm\Yclients\YclientsGateway;
use App\Enums\CrmProvider;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CrmGatewayResolverTest extends TestCase
{
    public function test_resolves_registered_gateway_by_provider(): void
    {
        $yclients = new YclientsGateway('https://yc.test', null);
        $resolver = new CrmGatewayResolver([$yclients]);

        $this->assertSame($yclients, $resolver->for(CrmProvider::Yclients));
    }

    public function test_throws_for_unregistered_provider(): void
    {
        $resolver = new CrmGatewayResolver([]);

        $this->expectException(InvalidArgumentException::class);
        $resolver->for(CrmProvider::Yclients);
    }
}
