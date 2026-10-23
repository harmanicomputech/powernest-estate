<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Http\Middleware\ResolveEstateContext;
use App\Integrations\Vending\ElectricityVendor;
use App\Integrations\Vending\StronVendor;
use App\Models\Estate;
use App\Models\User;
use App\Support\EstateContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(EstateContext::class);
        $this->app->bind(ElectricityVendor::class, StronVendor::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Livewire update requests re-run the estate resolver so components see the same verified context.
        Livewire::addPersistentMiddleware([ResolveEstateContext::class]);

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->mixedCase()->numbers()->uncompromised()
            : Password::min(8)->letters()->numbers());

        // Every estate permission is a gate: @can('visitors.invite'), $this->authorize('power.reconcile').
        // The estate defaults to the verified current estate; it is never taken from request input.
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, function (User $user, ?Estate $estate = null) use ($permission) {
                $estate ??= app(EstateContext::class)->estateOrNull();

                return $estate !== null && $user->hasEstatePermission($estate, $permission);
            });
        }

        Gate::define('platform.admin', fn (User $user) => $user->isPlatformAdmin() && $user->isActive());

        RateLimiter::for('gate-verify', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('power-purchase', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('meter-lookup', fn (Request $request) => Limit::perMinute(15)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(300)->by($request->ip()));
        RateLimiter::for('public-pass', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}
