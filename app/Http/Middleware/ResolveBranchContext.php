<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class ResolveBranchContext
{
    /**
     * Hydrate the branch context used by scoped models, jobs, and logs.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Esta cuenta esta desactivada.',
            ]);
        }

        $user->loadMissing('branches');

        $activeBranchId = $this->resolveActiveBranchId($user);

        Context::add([
            'branch_id' => $activeBranchId,
            'user_id' => $user->getKey(),
        ]);

        Context::addHidden('branch_scope_bypass', $user->hasGlobalBranchAccess());

        if ($activeBranchId !== null && $user->active_branch_id !== $activeBranchId) {
            $user->forceFill(['active_branch_id' => $activeBranchId])->saveQuietly();
        }

        return $next($request);
    }

    private function resolveActiveBranchId(User $user): ?int
    {
        if ($user->active_branch_id !== null && $user->canAccessBranch($user->active_branch_id)) {
            return $user->active_branch_id;
        }

        $fallbackBranchId = $user->branches
            ->sortByDesc('is_headquarters')
            ->sortBy('name')
            ->first()?->getKey();

        return $fallbackBranchId !== null ? (int) $fallbackBranchId : null;
    }
}
