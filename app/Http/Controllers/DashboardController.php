<?php

namespace App\Http\Controllers;

use App\Support\UserHome;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|SymfonyResponse
    {
        $homePath = UserHome::pathFor($request->user());

        if ($homePath !== route('dashboard', absolute: false)) {
            return redirect($homePath);
        }

        return Inertia::render('dashboard');
    }
}
