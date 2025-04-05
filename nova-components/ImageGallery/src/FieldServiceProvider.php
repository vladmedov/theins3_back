<?php

namespace Medov\ImageGallery;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Nova;

class FieldServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->booted(function () {
            $this->routes();
        });

        Nova::serving(function (ServingNova $event) {
            Nova::mix('image-gallery', __DIR__.'/../dist/mix-manifest.json');
        });
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    protected function routes()
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['nova'])
            ->prefix('nova-vendor/medov/image-gallery')
            ->group(__DIR__.'/../routes/api.php');
    }
}
