<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($this->canManageEmployees($request->user()), 403, 'Unauthorized.');

        $users = User::with(['roles', 'branches'])
            ->orderBy('name')
            ->get();
        $branches = Branch::orderBy('name')->get();
        $roles = Role::orderBy('name')->get();

        return Inertia::render('team/employees/index', [
            'users' => $users,
            'branches' => $branches,
            'roles' => $roles,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canManageEmployees($request->user()), 403, 'Unauthorized.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::default()],
            'branch_ids' => ['array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'role_names' => ['array'],
            'role_names.*' => ['string', 'exists:roles,name'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($validated): void {
            $branchIds = $validated['branch_ids'] ?? [];

            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'active_branch_id' => $branchIds[0] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->branches()->sync($branchIds);
            $user->syncRoles($validated['role_names'] ?? []);
        });

        return back()->with('success', 'Empleado creado correctamente.');
    }

    public function updateBranches(Request $request, User $user)
    {
        abort_unless($this->canManageEmployees($request->user()), 403, 'Unauthorized.');
        abort_if($request->user()->is($user), 422, 'No puedes modificar tus propias sucursales.');

        $validated = $request->validate([
            'branch_ids' => 'array',
            'branch_ids.*' => 'exists:branches,id',
        ]);

        $user->branches()->sync($validated['branch_ids'] ?? []);

        return back()->with('success', 'Sucursales actualizadas correctamente.');
    }

    public function updateRoles(Request $request, User $user)
    {
        abort_unless($this->canManageEmployees($request->user()), 403, 'Unauthorized.');
        abort_if($request->user()->is($user), 422, 'No puedes modificar tus propios roles.');

        $validated = $request->validate([
            'role_names' => 'array',
            'role_names.*' => 'exists:roles,name',
        ]);

        $user->syncRoles($validated['role_names'] ?? []);

        return back()->with('success', 'Roles actualizados correctamente.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        abort_unless($this->canManageEmployees($request->user()), 403, 'Unauthorized.');
        abort_if($request->user()->is($user), 422, 'No puedes desactivar tu propio usuario.');

        $validated = $request->validate([
            'is_active' => ['present', 'boolean'],
        ]);

        $user->forceFill(['is_active' => $validated['is_active']])->save();

        if (! $user->is_active) {
            DB::table('sessions')->where('user_id', $user->getKey())->delete();
        }

        return back()->with(
            'success',
            $user->is_active ? 'Usuario activado correctamente.' : 'Usuario desactivado correctamente.',
        );
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($this->canManageEmployees($request->user()), 403, 'Unauthorized.');
        abort_if($request->user()->is($user), 422, 'No puedes retirar tu propio acceso.');

        DB::transaction(function () use ($user): void {
            DB::table('sessions')->where('user_id', $user->getKey())->delete();
            $user->forceFill([
                'active_branch_id' => null,
                'is_active' => false,
            ])->save();
            $user->branches()->detach();
            $user->syncRoles([]);
        });

        return back()->with('success', 'Acceso retirado correctamente.');
    }

    private function canManageEmployees(?User $user): bool
    {
        return $user instanceof User
            && $user->hasAnyRole(config('internal.team_admin_roles'));
    }
}
