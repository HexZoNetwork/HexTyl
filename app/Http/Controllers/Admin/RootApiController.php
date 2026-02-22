<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\User;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RootApiController extends Controller
{
    public function __construct(private AlertsMessageBag $alert)
    {
    }

    /**
     * Show the root API key management page.
     * Only accessible by the root user.
     */
    public function index(Request $request)
    {
        if (!$request->user()->isRoot()) {
            throw new AccessDeniedHttpException('Only the root user may manage root API keys.');
        }

        $keys = ApiKey::where('user_id', $request->user()->id)
            ->whereIn('key_type', [ApiKey::TYPE_ROOT, ApiKey::TYPE_SUBROOT])
            ->get();

        return view('admin.api.root', ['keys' => $keys]);
    }

    /**
     * Generate a new root API key.
     */
    public function store(Request $request): RedirectResponse
    {
        if (!$request->user()->isRoot()) {
            throw new AccessDeniedHttpException('Only the root user may generate root API keys.');
        }

        $request->validate([
            'memo' => 'required|string|max:500',
            'key_tier' => 'nullable|in:root,subroot',
        ]);

        $tier = (string) $request->input('key_tier', 'subroot');
        $keyType = $tier === 'root' ? ApiKey::TYPE_ROOT : ApiKey::TYPE_SUBROOT;

        $token = Str::random(ApiKey::KEY_LENGTH);

        $key = ApiKey::create([
            'user_id'    => $request->user()->id,
            'key_type'   => $keyType,
            'identifier' => ApiKey::generateTokenIdentifier($keyType),
            'token'      => encrypt($token),
            'memo'       => $request->input('memo'),
            'allowed_ips' => [],
            // Grant full R/W to all resources on the application API also.
            'r_servers'          => 3,
            'r_nodes'            => 3,
            'r_allocations'      => 3,
            'r_users'            => 3,
            'r_locations'        => 3,
            'r_nests'            => 3,
            'r_eggs'             => 3,
            'r_database_hosts'   => 3,
            'r_server_databases' => 3,
        ]);

        // Flash the full key once — it will never be shown again.
        $fullKey = $key->identifier . $token;
        $label = $keyType === ApiKey::TYPE_ROOT ? 'Root' : 'Subroot';
        $this->alert->success("{$label} API key generated. Copy it now — it will not be shown again: <code>{$fullKey}</code>")->flash();

        return redirect()->route('admin.api.root');
    }

    /**
     * Revoke a root API key.
     */
    public function delete(Request $request, string $identifier): RedirectResponse
    {
        if (!$request->user()->isRoot()) {
            throw new AccessDeniedHttpException('Only the root user may revoke root API keys.');
        }

        ApiKey::where('identifier', $identifier)
            ->whereIn('key_type', [ApiKey::TYPE_ROOT, ApiKey::TYPE_SUBROOT])
            ->delete();

        $this->alert->success('Root API key revoked.')->flash();

        return redirect()->route('admin.api.root');
    }
}
