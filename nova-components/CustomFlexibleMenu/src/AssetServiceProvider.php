<?php

namespace Medov\CustomFlexibleMenu;

use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Nova;

class AssetServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Nova::serving(function (ServingNova $event) {
            Nova::mix('custom-flexible-menu', __DIR__.'/../dist/mix-manifest.json');
        });
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
