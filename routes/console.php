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
    ->everyFourHours()
    ->withoutOverlapping()
    ->onFailure(function () {
        \Log::error('UpdateCurrencyRates scheduled task failed');
    })
    ->onSuccess(function () {
        \Log::info('UpdateCurrencyRates scheduled task completed successfully');
    });

Schedule::command('update:oil')
    ->everyFourHours()
    ->withoutOverlapping()
    ->onFailure(function () {
        \Log::error('UpdateOilPrice scheduled task failed');
    })
    ->onSuccess(function () {
        \Log::info('UpdateOilPrice scheduled task completed successfully');
    });
