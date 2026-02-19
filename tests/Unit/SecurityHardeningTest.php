<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Pterodactyl\Http\Middleware\SecurityMiddleware;
use Pterodactyl\Services\Security\BehavioralScoreService;
use Pterodactyl\Services\Security\SilentDefenseService;
use Pterodactyl\Services\Security\ProgressiveSecurityModeService;
use Pterodactyl\Console\Commands\Security\ApplyDdosProfileCommand;

class SecurityHardeningTest extends TestCase
{
    public function testApplyDdosProfileRejectsInvalidWhitelist(): void
    {
        $command = new ApplyDdosProfileCommand();
        $method = new \ReflectionMethod($command, 'validatedWhitelist');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($command, '127.0.0.1,invalid-ip');
    }

    public function testApplyDdosProfileAcceptsIpv4AndIpv6Cidr(): void
    {
        $command = new ApplyDdosProfileCommand();
        $method = new \ReflectionMethod($command, 'validatedWhitelist');
        $method->setAccessible(true);

        $value = $method->invoke($command, '10.0.0.0/8,2001:db8::/32,127.0.0.1');

        $this->assertSame('10.0.0.0/8,2001:db8::/32,127.0.0.1', $value);
    }

    public function testSecurityMiddlewareWhitelistSupportsIpv6Cidr(): void
    {
        $middleware = new SecurityMiddleware(
            $this->createMock(BehavioralScoreService::class),
            $this->createMock(SilentDefenseService::class),
            $this->createMock(ProgressiveSecurityModeService::class),
        );

        $reflection = new ReflectionClass($middleware);
        $method = $reflection->getMethod('ipMatchesWhitelist');
        $method->setAccessible(true);

        $result = $method->invoke($middleware, '2001:db8::1234', ['2001:db8::/32']);

        $this->assertTrue($result);
    }
}
