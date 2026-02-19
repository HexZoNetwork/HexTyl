<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Pterodactyl\Models\Role;
use Pterodactyl\Models\RoleScope;
use Pterodactyl\Http\Controllers\Controller;
use Prologue\Alerts\AlertsMessageBag;

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
        return view('admin.roles.new');
    }

    /**
     * Store a new role.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:191|unique:roles,name',
            'description' => 'nullable|string|max:500',
        ]);

        $role = Role::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'is_system_role' => false,
        ]);

        $this->alert->success("Role '{$role->name}' created successfully.")->flash();
        return redirect()->route('admin.roles.view', $role->id);
    }

    /**
     * Show role edit page with scopes.
     */
    public function view(Role $role): View
    {
        $role->load('scopes');
        return view('admin.roles.view', compact('role'));
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
        $request->validate([
            'scope' => 'required|string|max:191',
        ]);

        $scope = trim($request->input('scope'));
        RoleScope::firstOrCreate(['role_id' => $role->id, 'scope' => $scope]);

        $this->alert->success("Scope '{$scope}' added to role '{$role->name}'.")->flash();
        return redirect()->route('admin.roles.view', $role->id);
    }

    /**
     * Remove a scope from a role.
     */
    public function removeScope(Role $role, RoleScope $scope): RedirectResponse
    {
        if ($scope->role_id !== $role->id) {
            abort(403);
        }
        $scope->delete();
        $this->alert->success('Scope removed.')->flash();
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
}
