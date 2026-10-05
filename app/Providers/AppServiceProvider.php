<?php

namespace App\Providers;

use App\Models\ParentChild;
use App\Models\PersonName;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Numeric ids keep /people/create etc. from being captured by /people/{person}.
        Route::patterns(array_fill_keys(
            ['person', 'story', 'event', 'media', 'album', 'place', 'question', 'interview', 'fact', 'link',
                'partnership', 'changeRequest', 'invitation', 'member', 'name'],
            '[0-9]+'
        ));
        Route::model('link', ParentChild::class);
        Route::model('name', PersonName::class);
    }
}
