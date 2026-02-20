<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Model;
use Pterodactyl\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Contracts\Translation\Translator;
use Pterodactyl\Services\Users\UserUpdateService;
use Pterodactyl\Traits\Helpers\AvailableLanguages;
use Pterodactyl\Services\Users\UserCreationService;
use Pterodactyl\Services\Users\UserDeletionService;
use Pterodactyl\Http\Requests\Admin\UserFormRequest;
use Pterodactyl\Http\Requests\Admin\NewUserFormRequest;
use Pterodactyl\Contracts\Repository\UserRepositoryInterface;

class UserController extends Controller
{
    use AvailableLanguages;

    /**
     * UserController constructor.
     */
    public function __construct(
        protected AlertsMessageBag $alert,
        protected UserCreationService $creationService,
        protected UserDeletionService $deletionService,
        protected Translator $translator,
        protected UserUpdateService $updateService,
        protected UserRepositoryInterface $repository,
        protected ViewFactory $view,
    ) {
    }

    /**
     * Display user index page.
     */
    public function index(Request $request): View
    {
        $users = QueryBuilder::for(
            User::query()->select('users.*')
                ->selectRaw('COUNT(DISTINCT(subusers.id)) as subuser_of_count')
                ->selectRaw('COUNT(DISTINCT(servers.id)) as servers_count')
                ->leftJoin('subusers', 'subusers.user_id', '=', 'users.id')
                ->leftJoin('servers', 'servers.owner_id', '=', 'users.id')
                ->groupBy('users.id')
        )
            ->allowedFilters(['username', 'email', 'uuid'])
            ->defaultSort('-root_admin')
            ->allowedSorts(['id', 'uuid'])
            ->paginate(50);

        return view('admin.users.index', ['users' => $users]);
    }

    /**
     * Display new user page.
     */
    public function create(): View
    {
        $actor = request()->user();

        return view('admin.users.new', [
            'languages' => $this->getAvailableLanguages(true),
            'roles' => $this->getAssignableRoles($actor),
        ]);
    }

    /**
     * Display user view page.
     */
    public function view(User $user): View
    {
        $actor = request()->user();

        return view('admin.users.view', [
            'user' => $user,
            'languages' => $this->getAvailableLanguages(true),
            'roles' => $this->getAssignableRoles($actor),
        ]);
    }

    /**
     * Delete a user from the system.
     *
     * @throws \Exception
     * @throws DisplayException
     */
    public function delete(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            throw new DisplayException(__('admin/user.exceptions.delete_self'));
        }

        if ($user->isRoot()) {
            throw new DisplayException('Cannot delete the system root user.');
        }

        $this->deletionService->handle($user);

        return redirect()->route('admin.users');
    }

    public function store(NewUserFormRequest $request): RedirectResponse
    {
        $roleId = $request->input('role_id');
        $isRootAdmin = (bool) $request->input('root_admin');
        if ($isRootAdmin && !$request->user()->isRoot()) {
            throw new DisplayException('Only root can grant administrator access.');
        }

        $this->ensureRoleAssignmentAllowed($request->user(), $roleId ? (int) $roleId : null);

        $user = $this->creationService->handle($request->normalize());
        $this->alert->success($this->translator->get('admin/user.notices.account_created'))->flash();

        return redirect()->route('admin.users.view', $user->id);
    }

    /**
     * Update a user on the system.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function update(UserFormRequest $request, User $user): RedirectResponse
    {
        if ($user->isRoot()) {
            throw new DisplayException('Cannot modify the system root user via the API.');
        }

        $roleId = $request->input('role_id');
        $isRootAdmin = (bool) $request->input('root_admin');
        if ($isRootAdmin && !$request->user()->isRoot()) {
            throw new DisplayException('Only root can grant administrator access.');
        }

        $this->ensureRoleAssignmentAllowed($request->user(), $roleId ? (int) $roleId : null);

        $this->updateService
            ->setUserLevel(User::USER_LEVEL_ADMIN)
            ->handle($user, $request->normalize());

        $this->alert->success(trans('admin/user.notices.account_updated'))->flash();

        return redirect()->route('admin.users.view', $user->id);
    }

    /**
     * Get a JSON response of users on the system.
     */
    public function json(Request $request): Model|Collection
    {
        $users = QueryBuilder::for(User::query())->allowedFilters(['email'])->paginate(25);

        // Handle single user requests.
        if ($request->query('user_id')) {
            $user = User::query()->findOrFail($request->input('user_id'));
            // @phpstan-ignore-next-line property.notFound
            $user->md5 = md5(strtolower($user->email));
            $user->append('avatar_url');

            return $user;
        }

        return $users->map(function ($item) {
            // @phpstan-ignore-next-line property.notFound
            $item->md5 = md5(strtolower($item->email));
            $item->append('avatar_url');

            return $item;
        });
    }

    private function canAssignRole(User $actor, Role $role): bool
    {
        if ($actor->isRoot()) {
            return true;
        }

        // Non-root cannot assign system roles.
        if ($role->is_system_role) {
            return false;
        }

        foreach ($role->scopes as $scope) {
            if ($scope->scope === '*' || !$actor->hasScope($scope->scope)) {
                return false;
            }
        }

        return true;
    }

    private function getAssignableRoles(User $actor): Collection
    {
        return Role::query()
            ->with('scopes')
            ->orderBy('id')
            ->get()
            ->filter(fn (Role $role) => $this->canAssignRole($actor, $role))
            ->values();
    }

    private function ensureRoleAssignmentAllowed(User $actor, ?int $roleId): void
    {
        if (empty($roleId)) {
            return;
        }

        $role = Role::query()->with('scopes')->findOrFail($roleId);
        if (!$this->canAssignRole($actor, $role)) {
            throw new DisplayException("You are not allowed to assign role '{$role->name}'.");
        }
    }
}
