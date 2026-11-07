<?php

namespace App\Providers;

use App\Models\ParentChild;
use App\Models\PersonName;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Numeric ids keep /people/create etc. from being captured by /people/{person}.
        Route::patterns(array_fill_keys(
            ['person', 'story', 'event', 'media', 'album', 'place', 'question', 'interview', 'fact', 'link',
                'partnership', 'changeRequest', 'invitation', 'member', 'name'],
            '[0-9]+'
        ));
        Route::model('link', ParentChild::class);
        Route::model('name', PersonName::class);

        $this->configureRateLimiting();
    }

    /**
     * Sign-in is limited per account *and* per address, so one guessed password list
     * cannot be run against an account from many addresses, nor against many accounts
     * from one address. Registration is limited per address only.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower(trim((string) $request->input('login'))).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
    }
}
