<?php

namespace Medov\AccordionItems;

use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Nova;

class FieldServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Nova::serving(function (ServingNova $event) {
            Nova::mix('accordion-items', __DIR__.'/../dist/mix-manifest.json');
        });
    }

    public function register(): void
    {
        //
    }
}
