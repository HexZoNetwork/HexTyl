<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Pterodactyl\Http\Middleware\SecurityMiddleware;
use Pterodactyl\Services\Security\BehavioralScoreService;
use Pterodactyl\Services\Security\SilentDefenseService;
use Pterodactyl\Services\Security\ProgressiveSecurityModeService;
use Pterodactyl\Services\Security\NodeSecureModeService;
use Pterodactyl\Console\Commands\Security\ApplyDdosProfileCommand;
use Illuminate\Http\Request;

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

    public function testApplyDdosProfileRejectsInvalidCidrPrefix(): void
    {
        $command = new ApplyDdosProfileCommand();
        $method = new \ReflectionMethod($command, 'validatedWhitelist');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($command, '203.0.113.0/99');
    }

    public function testSecurityMiddlewareWhitelistSupportsIpv6Cidr(): void
    {
        $middleware = $this->newSecurityMiddleware();

        $reflection = new ReflectionClass($middleware);
        $method = $reflection->getMethod('ipMatchesWhitelist');
        $method->setAccessible(true);

        $result = $method->invoke($middleware, '2001:db8::1234', ['2001:db8::/32']);

        $this->assertTrue($result);
    }

    public function testSecurityMiddlewareValidWhitelistEntrySupportsPublicIpv4Cidr(): void
    {
        $middleware = $this->newSecurityMiddleware();

        $reflection = new ReflectionClass($middleware);
        $method = $reflection->getMethod('isValidWhitelistEntry');
        $method->setAccessible(true);

        $result = $method->invoke($middleware, '203.0.113.0/24');

        $this->assertTrue($result);
    }

    public function testSecurityMiddlewareValidWhitelistEntryRejectsInvalidCidrPrefix(): void
    {
        $middleware = $this->newSecurityMiddleware();

        $reflection = new ReflectionClass($middleware);
        $method = $reflection->getMethod('isValidWhitelistEntry');
        $method->setAccessible(true);

        $result = $method->invoke($middleware, '2001:db8::/129');

        $this->assertFalse($result);
    }

    public function testSecurityMiddlewareRootUserBypassNonRootApplicationPath(): void
    {
        $middleware = $this->newSecurityMiddleware();
        $request = Request::create('/api/client', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.10']);
        $user = $this->getMockBuilder(\stdClass::class)->addMethods(['isRoot'])->getMock();
        $user->method('isRoot')->willReturn(true);
        $request->setUserResolver(fn () => $user);

        $reflection = new ReflectionClass($middleware);
        $method = $reflection->getMethod('shouldBypassDdosProtection');
        $method->setAccessible(true);

        $result = $method->invoke($middleware, $request);

        $this->assertTrue($result);
    }

    public function testSecurityMiddlewareRootUserDoesNotBypassRootApplicationPath(): void
    {
        $middleware = $this->newSecurityMiddleware();
        $request = Request::create('/api/rootapplication/overview', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.11']);
        $user = $this->getMockBuilder(\stdClass::class)->addMethods(['isRoot'])->getMock();
        $user->method('isRoot')->willReturn(true);
        $request->setUserResolver(fn () => $user);

        $reflection = new ReflectionClass($middleware);
        $settingsMemo = $reflection->getProperty('settingsMemo');
        $settingsMemo->setAccessible(true);
        $settingsMemo->setValue($middleware, ['ddos_whitelist_ips' => '']);

        $method = $reflection->getMethod('shouldBypassDdosProtection');
        $method->setAccessible(true);

        $result = $method->invoke($middleware, $request);

        $this->assertFalse($result);
    }

    private function newSecurityMiddleware(): SecurityMiddleware
    {
        return new SecurityMiddleware(
            $this->createMock(BehavioralScoreService::class),
            $this->createMock(SilentDefenseService::class),
            $this->createMock(ProgressiveSecurityModeService::class),
            $this->createMock(NodeSecureModeService::class),
        );
    }
}
