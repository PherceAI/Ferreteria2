<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Domain\Purchasing\Services\GmailOAuthService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class GmailOAuthController extends Controller
{
    public function __construct(
        private readonly GmailOAuthService $oauthService,
    ) {}

    public function connect(Request $request): RedirectResponse
    {
        abort_unless($this->canManageGmail($request->user()), 403);

        $state = Str::random(40);
        $request->session()->put('gmail_oauth_state', $state);

        return redirect()->away($this->oauthService->getAuthUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless($this->canManageGmail($request->user()), 403);

        $code = $request->string('code')->value();
        $error = $request->string('error')->value();
        $state = $request->string('state')->value();
        $expectedState = (string) $request->session()->pull('gmail_oauth_state', '');

        if ($expectedState === '' || ! hash_equals($expectedState, $state)) {
            Log::warning('[GmailOAuth] Callback con state invalido.');

            return redirect()->route('purchasing.index')
                ->with('error', 'No se pudo validar la autorizacion de Gmail. Intenta de nuevo.');
        }

        if ($error !== '' || $code === '') {
            Log::warning('[GmailOAuth] Callback con error o sin codigo: '.$error);

            return redirect()->route('purchasing.index')
                ->with('error', 'No se pudo autorizar el acceso a Gmail. Intenta de nuevo.');
        }

        try {
            $this->oauthService->exchangeCode($code);
        } catch (Throwable $e) {
            Log::error('[GmailOAuth] Error intercambiando codigo: '.$e->getMessage());

            return redirect()->route('purchasing.index')
                ->with('error', 'No se pudo completar la conexion de Gmail. Revisa la configuracion OAuth e intenta de nuevo.');
        }

        return redirect()->route('purchasing.index')
            ->with('success', 'Gmail conectado correctamente. Las facturas se procesaran cada 15 minutos.');
    }

    private function canManageGmail(?User $user): bool
    {
        return $user instanceof User
            && ($user->hasGlobalBranchAccess() || $user->hasAnyRole(config('internal.gmail_oauth_roles')));
    }
}
