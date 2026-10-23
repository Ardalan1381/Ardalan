<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()->with('role')->orderBy('name')->paginate(SystemSetting::get('pagination_per_page', 20));

        return view('admin.users.index', ['users' => $users]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', ['roles' => Role::orderBy('label')->get()]);
    }

    public function store(StoreUserRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role_id' => $data['role_id'],
        ]);

        $auditLogger->log('user.created', $user, new: ['name' => $user->name, 'email' => $user->email, 'role_id' => $user->role_id]);

        return redirect()->route('admin.users.index')->with('status', __('admin.flash.user_created'));
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', ['user' => $user, 'roles' => Role::orderBy('label')->get()]);
    }

    public function update(UpdateUserRequest $request, User $user, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validated();
        $originalRoleId = $user->role_id;

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role_id' => $data['role_id'],
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        $auditLogger->log('user.updated', $user, old: ['role_id' => $originalRoleId], new: ['role_id' => $user->role_id]);

        if ($originalRoleId !== $user->role_id) {
            $auditLogger->log('user.role_changed', $user, old: ['role_id' => $originalRoleId], new: ['role_id' => $user->role_id]);
        }

        return redirect()->route('admin.users.index')->with('status', __('admin.flash.user_updated'));
    }

    public function toggleActive(User $user, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('deactivate', $user);

        if ($user->is_active && $user->hasRole('owner') && User::where('role_id', $user->role_id)->where('is_active', true)->count() <= 1) {
            return redirect()->route('admin.users.index')->with('error', __('admin.flash.cannot_deactivate_last_owner'));
        }

        $wasActive = $user->is_active;
        $user->update(['is_active' => ! $user->is_active]);

        $auditLogger->log('user.status_toggled', $user, old: ['is_active' => $wasActive], new: ['is_active' => $user->is_active]);

        return redirect()->route('admin.users.index')->with('status', __('admin.flash.user_status_updated'));
    }
}
