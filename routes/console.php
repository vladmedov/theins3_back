<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('process:view-counts')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onFailure(function () {
        \Log::error('ProcessViewCounts scheduled task failed');
    })
    ->onSuccess(function () {
        \Log::info('ProcessViewCounts scheduled task completed successfully');
    });

Schedule::command('update:currencies')
    ->hourly()
    ->withoutOverlapping()
    ->onFailure(function () {
        \Log::error('UpdateCurrencyRates scheduled task failed');
    })
    ->onSuccess(function () {
        \Log::info('UpdateCurrencyRates scheduled task completed successfully');
    });

Schedule::command('update:oil')
    ->everyEightHours()
    ->withoutOverlapping()
    ->onFailure(function () {
        \Log::error('UpdateOilPrice scheduled task failed');
    })
    ->onSuccess(function () {
        \Log::info('UpdateOilPrice scheduled task completed successfully');
    });

Schedule::command('generate:sitemap')
    ->hourly()
    ->withoutOverlapping()
    ->onFailure(function () {
        \Log::error('GenerateSitemap scheduled task failed');
    })
    ->onSuccess(function () {
        \Log::info('GenerateSitemap scheduled task completed successfully');
    });

Schedule::command('scout:import "App\\Models\\Post"')
    ->dailyAt('00:00')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Log::error('Scout import scheduled task failed');
    })
    ->onSuccess(function () {
        \Log::info('Scout import scheduled task completed successfully');
    });

// Schedule::command('sync:legacy')
//     ->everyMinute()
//     ->withoutOverlapping()
//     ->onFailure(function () {
//         \Log::error('Legacy sync scheduled task failed');
//     })
//     ->onSuccess(function () {
//         \Log::info('Legacy sync scheduled task completed successfully');
//     });
