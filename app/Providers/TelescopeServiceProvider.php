<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters([
            '_token',
            'access_token',
            'auth',
            'client_secret',
            'code',
            'current_password',
            'endpoint',
            'idempotency_key',
            'key',
            'password',
            'password_confirmation',
            'refresh_token',
            'state',
            'token',
            'two_factor_recovery_codes',
            'two_factor_secret',
        ]);

        Telescope::hideRequestHeaders([
            'authorization',
            'cookie',
            'php-auth-pw',
            'php-auth-user',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function (?User $user) {
            return $user instanceof User
                && (in_array($user->email, config('internal.observability_emails'), true)
                    || $user->hasRole(config('internal.observability_roles')));
        });
    }
}
