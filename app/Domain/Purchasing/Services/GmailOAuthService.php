<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Services;

use App\Domain\Purchasing\Models\GmailOAuthToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GmailOAuthService
{
    public function getAuthUrl(): string
    {
        $params = http_build_query([
            'client_id' => config('gmail_inbox.client_id'),
            'redirect_uri' => config('gmail_inbox.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', config('gmail_inbox.scopes')),
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        return config('gmail_inbox.auth_endpoint').'?'.$params;
    }

    public function exchangeCode(string $code): GmailOAuthToken
    {
        $response = Http::asForm()->post(config('gmail_inbox.token_endpoint'), [
            'code' => $code,
            'client_id' => config('gmail_inbox.client_id'),
            'client_secret' => config('gmail_inbox.client_secret'),
            'redirect_uri' => config('gmail_inbox.redirect_uri'),
            'grant_type' => 'authorization_code',
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Google OAuth token exchange failed: '.$response->body());
        }

        $data = $response->json();
        $email = $this->fetchProfileEmail($data['access_token']);

        return GmailOAuthToken::updateOrCreate([
            'email' => $email,
        ], [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token']
                ?? GmailOAuthToken::query()->where('email', $email)->value('refresh_token'),
            'token_type' => $data['token_type'] ?? 'Bearer',
            'is_active' => true,
            'expires_at' => Carbon::now()->addSeconds($data['expires_in'] - 60),
            'connected_at' => now(),
        ]);
    }

    /**
     * @throws RuntimeException si no hay token configurado
     * @throws ConnectionException si falla el refresh
     */
    public function getValidAccessToken(?GmailOAuthToken $token = null): string
    {
        $token ??= GmailOAuthToken::query()
            ->where('is_active', true)
            ->latest()
            ->first();

        if ($token === null) {
            throw new RuntimeException('Gmail OAuth no configurado. Visita /purchasing/gmail/connect para autorizar.');
        }

        if (! $token->isExpired()) {
            $token->forceFill(['last_used_at' => now()])->save();

            return $token->access_token;
        }

        return $this->refreshToken($token);
    }

    /**
     * @return iterable<GmailOAuthToken>
     */
    public function activeTokens(): iterable
    {
        return GmailOAuthToken::query()
            ->where('is_active', true)
            ->orderBy('email')
            ->get();
    }

    private function refreshToken(GmailOAuthToken $token): string
    {
        $response = Http::asForm()->post(config('gmail_inbox.token_endpoint'), [
            'refresh_token' => $token->refresh_token,
            'client_id' => config('gmail_inbox.client_id'),
            'client_secret' => config('gmail_inbox.client_secret'),
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Gmail token refresh failed: '.$response->body());
        }

        $data = $response->json();

        $token->update([
            'access_token' => $data['access_token'],
            'token_type' => $data['token_type'] ?? 'Bearer',
            'expires_at' => Carbon::now()->addSeconds($data['expires_in'] - 60),
            'last_used_at' => now(),
        ]);

        return $data['access_token'];
    }

    private function fetchProfileEmail(string $accessToken): string
    {
        $response = Http::withToken($accessToken)
            ->get('https://gmail.googleapis.com/gmail/v1/users/me/profile');

        if ($response->failed()) {
            throw new RuntimeException('Gmail profile lookup failed: '.$response->body());
        }

        $email = $response->json('emailAddress');

        if (! is_string($email) || $email === '') {
            throw new RuntimeException('Google no devolvio el correo autorizado.');
        }

        return $email;
    }
}
