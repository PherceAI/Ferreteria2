<?php

namespace App\Support;

use App\Models\User;

class UserHome
{
    public static function pathFor(User $user): string
    {
        if ($user->hasRole('Bodeguero') && ! $user->hasGlobalBranchAccess()) {
            return route('purchasing.receipt.index', absolute: false);
        }

        return route('dashboard', absolute: false);
    }
}
