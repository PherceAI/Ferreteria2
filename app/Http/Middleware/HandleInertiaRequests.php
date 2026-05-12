<?php

namespace App\Http\Middleware;

use App\Domain\Dashboard\Services\OperationalAlertService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'activeBranch' => $user?->activeBranch,
                'branches' => $user?->branches()->orderByDesc('is_headquarters')->orderBy('name')->get(),
                'canViewAllBranches' => $user?->hasGlobalBranchAccess() ?? false,
                'roles' => $user?->roles()->pluck('name')->values() ?? [],
            ],
            'operationalAlerts' => fn () => $user
                ? app(OperationalAlertService::class)->forUser($user)
                : [],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Clave pública VAPID para que el frontend pueda suscribirse al push
            // Es pública por diseño (el browser la necesita para cifrar la suscripción)
            'vapidPublicKey' => config('webpush.vapid.public_key'),
        ];
    }
}
