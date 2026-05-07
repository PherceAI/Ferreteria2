<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        // Require global branch access
        abort_unless($request->user()->hasGlobalBranchAccess(), 403, 'Unauthorized.');

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

    public function updateBranches(Request $request, User $user)
    {
        abort_unless($request->user()->hasGlobalBranchAccess(), 403, 'Unauthorized.');

        $validated = $request->validate([
            'branch_ids' => 'array',
            'branch_ids.*' => 'exists:branches,id',
        ]);

        $user->branches()->sync($validated['branch_ids'] ?? []);

        return back()->with('success', 'Sucursales actualizadas correctamente.');
    }

    public function updateRoles(Request $request, User $user)
    {
        abort_unless($request->user()->hasGlobalBranchAccess(), 403, 'Unauthorized.');

        $validated = $request->validate([
            'role_names' => 'array',
            'role_names.*' => 'exists:roles,name',
        ]);

        $user->syncRoles($validated['role_names'] ?? []);

        return back()->with('success', 'Roles actualizados correctamente.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->hasGlobalBranchAccess(), 403, 'Unauthorized.');
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
        abort_unless($request->user()->hasGlobalBranchAccess(), 403, 'Unauthorized.');
        abort_if($request->user()->is($user), 422, 'No puedes eliminar tu propio usuario.');

        DB::transaction(function () use ($user): void {
            DB::table('sessions')->where('user_id', $user->getKey())->delete();
            $user->branches()->detach();
            $user->syncRoles([]);
            $user->delete();
        });

        return back()->with('success', 'Usuario eliminado correctamente.');
    }
}
