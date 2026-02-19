<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Pterodactyl\Models\Role;
use Pterodactyl\Models\RoleScope;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Services\Admins\RoleTemplateService;

class RoleController extends Controller
{
    public function __construct(protected AlertsMessageBag $alert) {}

    /**
     * List all roles.
     */
    public function index(): View
    {
        $roles = Role::withCount('users')->with('scopes')->orderBy('id')->get();
        return view('admin.roles.index', compact('roles'));
    }

    /**
     * Show create form.
     */
    public function create(): View
    {
        return view('admin.roles.new', [
            'templates' => RoleTemplateService::templates(),
            'availableScopes' => $this->availableScopeCatalog(),
        ]);
    }

    /**
     * Store a new role.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:191|unique:roles,name',
            'description' => 'nullable|string|max:500',
            'mode' => 'required|in:template,manual',
            'template' => 'required_if:mode,template|string',
            'scopes' => 'required_if:mode,manual|array',
            'scopes.*' => 'string|max:191',
        ]);

        $mode = (string) $request->input('mode', 'template');
        $templates = RoleTemplateService::templates();
        $templateKey = (string) $request->input('template', '');
        if ($mode === 'template' && !isset($templates[$templateKey])) {
            throw new DisplayException('Invalid role template selected.');
        }

        $selectedScopes = [];
        if ($mode === 'template') {
            $selectedScopes = $templates[$templateKey]['scopes'];
        } else {
            $selectedScopes = collect($request->input('scopes', []))
                ->map(fn ($scope) => trim((string) $scope))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($selectedScopes)) {
                throw new DisplayException('Manual mode requires at least one scope.');
            }
        }

        $actor = $request->user();
        if (!$actor->isRoot()) {
            foreach ($selectedScopes as $scope) {
                if ($scope === '*' || !$actor->hasScope($scope)) {
                    throw new DisplayException("You cannot grant scope '{$scope}' because you do not possess it.");
                }
            }
        }

        $description = $request->input('description');
        if (empty($description) && $mode === 'template') {
            $description = $templates[$templateKey]['description'];
        }

        $role = Role::create([
            'name' => $request->input('name'),
            'description' => $description,
            'is_system_role' => false,
        ]);

        foreach ($selectedScopes as $scope) {
            RoleScope::firstOrCreate(['role_id' => $role->id, 'scope' => $scope]);
        }

        $this->alert->success("Role '{$role->name}' created successfully.")->flash();
        return redirect()->route('admin.roles.view', $role->id);
    }

    /**
     * Show role edit page with scopes.
     */
    public function view(Role $role): View
    {
        $role->load('scopes');

        return view('admin.roles.view', [
            'role' => $role,
            'availableScopes' => $this->availableScopeCatalog(),
        ]);
    }

    /**
     * Update a role's name and description.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->is_system_role) {
            $this->alert->danger('System roles cannot be renamed or modified.')->flash();
            return redirect()->route('admin.roles.view', $role->id);
        }

        $request->validate([
            'name' => 'required|string|max:191|unique:roles,name,' . $role->id,
            'description' => 'nullable|string|max:500',
        ]);

        $role->update([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
        ]);

        $this->alert->success('Role updated successfully.')->flash();
        return redirect()->route('admin.roles.view', $role->id);
    }

    /**
     * Add a scope to a role.
     */
    public function addScope(Request $request, Role $role): RedirectResponse
    {
        if ($role->is_system_role) {
            throw new DisplayException('System roles cannot be modified.');
        }

        $data = $request->validate([
            'scopes' => 'required|array|min:1',
            'scopes.*' => 'required|string|max:191',
        ]);

        $actor = $request->user();
        $scopeCatalog = $this->availableScopeCatalog();

        foreach ($data['scopes'] as $scope) {
            $scope = trim((string) $scope);
            if ($scope === '') {
                continue;
            }

            if (!$scopeCatalog->contains($scope)) {
                throw new DisplayException("Unknown scope: {$scope}");
            }

            if (!$actor->isRoot() && !$actor->hasScope($scope)) {
                throw new DisplayException("You cannot grant scope '{$scope}' because you do not possess it.");
            }

            RoleScope::firstOrCreate([
                'role_id' => $role->id,
                'scope' => $scope,
            ]);
        }

        $this->alert->success('Scope(s) added successfully.')->flash();

        return redirect()->route('admin.roles.view', $role->id);
    }

    /**
     * Remove a scope from a role.
     */
    public function removeScope(Role $role, RoleScope $scope): RedirectResponse
    {
        if ($role->is_system_role) {
            throw new DisplayException('System roles cannot be modified.');
        }

        if ($scope->role_id !== $role->id) {
            throw new DisplayException('Scope does not belong to this role.');
        }

        $scope->delete();

        $this->alert->success('Scope removed successfully.')->flash();

        return redirect()->route('admin.roles.view', $role->id);
    }

    /**
     * Delete a role (non-system only).
     */
    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system_role) {
            $this->alert->danger('System roles cannot be deleted.')->flash();
            return redirect()->route('admin.roles');
        }

        $roleName = $role->name;
        $role->delete();

        $this->alert->success("Role '{$roleName}' deleted.")->flash();
        return redirect()->route('admin.roles');
    }

    private function availableScopeCatalog(): Collection
    {
        $baseScopes = collect([
            '*',
            'admin:read_only',
            'server:private:view',
            'user.read',
            'user.create',
            'user.update',
            'user.delete',
            'user.admin.create',
            'server.read',
            'server.create',
            'server.update',
            'server.delete',
            'node.read',
            'node.write',
            'database.read',
            'database.create',
            'database.update',
            'database.delete',
            'database.view_password',
            'websocket.connect',
            'control.console',
            'control.start',
            'control.stop',
            'control.restart',
            'control.command',
            'control.allocation',
            'file.read',
            'file.read-content',
            'file.create',
            'file.update',
            'file.delete',
            'file.archive',
            'file.sftp',
            'backup.read',
            'backup.create',
            'backup.delete',
            'backup.download',
            'backup.restore',
            'schedule.read',
            'schedule.create',
            'schedule.update',
            'schedule.delete',
            'schedule.execute',
            'startup.read',
            'startup.update',
            'settings.rename',
            'settings.reinstall',
            'activity.read',
            'chat.read',
            'chat.create',
        ]);

        $templateScopes = collect(RoleTemplateService::templates())
            ->pluck('scopes')
            ->flatten(1);

        return RoleScope::query()
            ->select('scope')
            ->distinct()
            ->pluck('scope')
            ->merge($templateScopes)
            ->merge($baseScopes)
            ->map(fn ($scope) => trim((string) $scope))
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }
}
