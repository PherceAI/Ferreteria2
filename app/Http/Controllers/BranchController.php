<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BranchController extends Controller
{
    /**
     * Switch the authenticated user's active branch.
     *
     * The branch context is hydrated on the next request by
     * ResolveBranchContext middleware, which reads active_branch_id.
     */
    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where('is_active', true),
            ],
        ]);

        $user = $request->user();
        $branchId = (int) $validated['branch_id'];

        if (! $user->canAccessBranch($branchId)) {
            throw new AccessDeniedHttpException;
        }

        $user->forceFill(['active_branch_id' => $branchId])->save();

        return back();
    }
}
