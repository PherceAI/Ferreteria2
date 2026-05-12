<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Pulse\Facades\Pulse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureInternalAccess();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureInternalAccess(): void
    {
        Gate::define('viewPulse', function (?User $user) {
            return $this->canAccessObservability($user);
        });

        Pulse::user(fn (User $user): array => [
            'name' => $user->name,
            'extra' => $user->email,
            'avatar' => null,
        ]);
    }

    protected function canAccessObservability(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return in_array($user->email, config('internal.observability_emails'), true)
            || $user->hasRole(config('internal.observability_roles'));
    }
}
