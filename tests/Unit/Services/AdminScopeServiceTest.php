<?php

namespace Tests\Unit\Services;

use Mockery;
use PHPUnit\Framework\TestCase;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Admins\AdminScopeService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AdminScopeServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testPrivateServerCreationOnlyNeedsCreateScope(): void
    {
        $service = new AdminScopeService();

        $admin = Mockery::mock(User::class);
        $admin->shouldReceive('isRoot')->andReturn(false);
        $admin->shouldReceive('hasScope')->with('server.create')->andReturn(true);

        $service->ensureCanCreateWithVisibility($admin, Server::VISIBILITY_PRIVATE);

        $this->assertTrue(true);
    }

    public function testCreateWithoutCreateScopeIsDenied(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Missing required scope: server.create');

        $service = new AdminScopeService();

        $admin = Mockery::mock(User::class);
        $admin->shouldReceive('isRoot')->andReturn(false);
        $admin->shouldReceive('hasScope')->with('server.create')->andReturn(false);

        $service->ensureCanCreateWithVisibility($admin, Server::VISIBILITY_PRIVATE);
    }
}

