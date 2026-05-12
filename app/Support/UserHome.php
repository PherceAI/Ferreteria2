<?php

namespace App\Support;

use App\Models\User;

class UserHome
{
    public static function pathFor(User $user): string
    {
        if ($user->hasAnyRole(config('internal.warehouse_roles', [])) && ! $user->hasGlobalBranchAccess()) {
            return route('purchasing.receipt.index', absolute: false);
        }

        return route('dashboard', absolute: false);
    }
}
