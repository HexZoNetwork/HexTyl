<?php

namespace Pterodactyl\Http\Controllers\Root;

use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\Nest;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Http\Controllers\Controller;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RootPanelController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /** Enforce root-only access for all root panel methods. */
    private function requireRoot(Request $request): void
    {
        if (!$request->user() || !$request->user()->isRoot()) {
            throw new AccessDeniedHttpException('Root panel access is restricted to the root account.');
        }
    }

    /** Root Panel Dashboard */
    public function index(Request $request)
    {
        $this->requireRoot($request);

        $stats = [
            'users'   => User::count(),
            'servers' => Server::count(),
            'nodes'   => Node::count(),
            'nests'   => Nest::count(),
            'eggs'    => Egg::count(),
            'api_keys' => ApiKey::count(),
            'root_keys' => ApiKey::where('key_type', ApiKey::TYPE_ROOT)->count(),
            'public_servers' => Server::where('visibility', 'public')->count(),
            'private_servers' => Server::where('visibility', 'private')->count(),
            'suspended' => Server::where('status', 'suspended')->count(),
        ];

        return view('root.dashboard', compact('stats'));
    }

    /** Root Users Management */
    public function users(Request $request)
    {
        $this->requireRoot($request);
        $users = User::withCount(['servers'])->orderBy('id')->paginate(50);
        return view('root.users', compact('users'));
    }

    /** Root Servers Management */
    public function servers(Request $request)
    {
        $this->requireRoot($request);
        $servers = Server::with(['user', 'node', 'nest', 'egg'])->orderBy('id')->paginate(50);
        return view('root.servers', compact('servers'));
    }

    /** Root Nodes Management */
    public function nodes(Request $request)
    {
        $this->requireRoot($request);
        $nodes = Node::withCount(['servers'])->with(['location'])->orderBy('id')->paginate(50);
        return view('root.nodes', compact('nodes'));
    }

    /** Root API Keys — both regular and root keys across all users */
    public function apiKeys(Request $request)
    {
        $this->requireRoot($request);
        $keys = ApiKey::with(['user'])->orderBy('created_at', 'desc')->paginate(100);
        return view('root.api_keys', compact('keys'));
    }

    /** Revoke any API key system-wide */
    public function revokeKey(Request $request, string $identifier)
    {
        $this->requireRoot($request);
        ApiKey::where('identifier', $identifier)->delete();
        return redirect()->route('root.api_keys')->with('success', 'API key revoked.');
    }

    /** Suspend / unsuspend a user */
    public function toggleUserSuspension(Request $request, User $user)
    {
        $this->requireRoot($request);
        $user->update(['suspended' => !$user->suspended]);
        return redirect()->route('root.users')->with('success', 'User suspension state toggled.');
    }

    /** Force delete a server */
    public function deleteServer(Request $request, Server $server)
    {
        $this->requireRoot($request);
        $server->delete();
        return redirect()->route('root.servers')->with('success', 'Server deleted.');
    }
}
