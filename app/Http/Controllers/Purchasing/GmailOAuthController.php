<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Domain\Purchasing\Services\GmailOAuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GmailOAuthController extends Controller
{
    public function __construct(
        private readonly GmailOAuthService $oauthService,
    ) {}

    public function connect(): RedirectResponse
    {
        return redirect()->away($this->oauthService->getAuthUrl());
    }

    public function callback(Request $request): RedirectResponse
    {
        $code = $request->string('code')->value();
        $error = $request->string('error')->value();

        if ($error !== '' || $code === '') {
            Log::warning('[GmailOAuth] Callback con error o sin código: '.$error);

            return redirect()->route('purchasing.index')
                ->with('error', 'No se pudo autorizar el acceso a Gmail. Intenta de nuevo.');
        }

        try {
            $this->oauthService->exchangeCode($code);
        } catch (Throwable $e) {
            Log::error('[GmailOAuth] Error intercambiando código: '.$e->getMessage());

            return redirect()->route('purchasing.index')
                ->with('error', 'Error al conectar Gmail: '.$e->getMessage());
        }

        return redirect()->route('purchasing.index')
            ->with('success', 'Gmail conectado correctamente. Las facturas se procesarán cada 15 minutos.');
    }
}
