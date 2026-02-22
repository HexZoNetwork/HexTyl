<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\User;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Acl\Api\AdminAcl;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Api\KeyCreationService;
use Pterodactyl\Http\Requests\Admin\Api\StoreApplicationApiKeyRequest;

class ApiController extends Controller
{
    /**
     * ApiController constructor.
     */
    public function __construct(
        private AlertsMessageBag $alert,
        private KeyCreationService $keyCreationService,
    ) {
    }

    /**
     * Render view showing all of a user's application API keys.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        return view('admin.api.index', [
            'keys' => ApiKey::query()
                ->with('user:id,username')
                ->where('key_type', ApiKey::TYPE_APPLICATION)
                ->when(!$user->isRoot(), fn ($query) => $query->where('user_id', $user->id))
                ->latest('id')
                ->get(),
        ]);
    }

    /**
     * Render view allowing an admin to create a new application API key.
     *
     * @throws \ReflectionException
     */
    public function create(): View
    {
        /** @var User|null $user */
        $user = request()->user();
        $scopeCatalog = $user ? AdminAcl::getCreationScopeCatalog($user) : [];

        return view('admin.api.new', [
            'scopeCatalog' => $scopeCatalog,
            'canCreateAny' => collect($scopeCatalog)->contains(fn (array $scope) => ($scope['assignable'] ?? false) === true),
        ]);
    }

    /**
     * Store the new key and redirect the user back to the application key listing.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function store(StoreApplicationApiKeyRequest $request): RedirectResponse
    {
        $user = $request->user();
        $permissions = AdminAcl::buildPermissionsFromScopes($user, $request->getRequestedScopes());

        if (!$user->isRoot() && collect($permissions)->every(fn ($value) => (int) $value === AdminAcl::NONE)) {
            throw new DisplayException('Your selected scopes do not grant any assignable PTLA permission for your current role.');
        }

        $this->keyCreationService->setKeyType(ApiKey::TYPE_APPLICATION)->handle([
            'memo' => $request->input('memo'),
            'user_id' => $request->user()->id,
        ], $permissions);

        $this->alert->success('A new application API key has been generated for your account.')->flash();

        return redirect()->route('admin.api.index');
    }

    /**
     * Delete an application API key from the database.
     */
    public function delete(Request $request, string $identifier): Response
    {
        $user = $request->user();
        ApiKey::query()
            ->where('key_type', ApiKey::TYPE_APPLICATION)
            ->where('identifier', $identifier)
            ->when(!$user->isRoot(), fn ($query) => $query->where('user_id', $user->id))
            ->delete();

        return response('', 204);
    }
}
