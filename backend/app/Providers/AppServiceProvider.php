<?php

namespace App\Providers;

use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

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
        $this->configureGates();
        $this->configureRateLimits();
        $this->configureRouteBindings();
    }

    /**
     * Route parameters resolved by something other than the id. Defined here, not in a routes file,
     * because cached routes skip the routes files.
     */
    protected function configureRouteBindings(): void
    {
        // The online shop API's {shop}: an active organization, found by its slug.
        Route::bind('shop', fn (string $slug): Organization => Organization::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail());
    }

    /**
     * Web shop orders arrive from the Next.js server, so every shopper shares its IP address. Limit instead
     * per shop (a flood guard, counting every attempt) and per shopper phone number (counting only orders
     * actually placed, so a shopper fixing typos in the form is not locked out).
     */
    protected function configureRateLimits(): void
    {
        RateLimiter::for('shop-orders', function (Request $request): array {
            $shop = $request->route('shop');
            $shopKey = 'shop-orders:'.($shop instanceof Organization ? $shop->slug : (string) $shop);
            $phoneDigits = preg_replace('/\D+/', '', (string) $request->input('phone'));

            return [
                Limit::perMinute(30)->by($shopKey),
                Limit::perHour(5)->by($shopKey.':'.$phoneDigits)
                    ->after(fn (Response $response): bool => $response->getStatusCode() === Response::HTTP_CREATED),
            ];
        });
    }

    /**
     * Define abilities that depend on the user's role in an organization.
     */
    protected function configureGates(): void
    {
        Gate::define('manage-members', fn (User $user, Organization $organization): bool => $organization->hasAdmin($user));
        Gate::define('super-admin', fn (User $user): bool => $user->isSuperAdmin());
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
}
