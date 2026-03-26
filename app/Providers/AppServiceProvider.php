<?php

namespace App\Providers;

use Elastic\Elasticsearch\Client;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;
use Matchish\ScoutElasticSearch\ElasticSearchServiceProvider;
use Matchish\ScoutElasticSearch\Engines\ElasticSearchEngine;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // В matchish/laravel-scout-elasticsearch 8.x биндинги клиента Elasticsearch
        // и HitsIteratorAggregate вынесены в отдельный провайдер.
        $this->app->register(ElasticSearchServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        RateLimiter::for('nova-post-edit-lock', function (Request $request) {
            return Limit::perMinute(120)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        // Backward-compatible alias: many installs use SCOUT_DRIVER=elasticsearch.
        // matchish registers the engine under ElasticSearchEngine::class.
        resolve(EngineManager::class)->extend('elasticsearch', function ($app) {
            $client = $app->make(Client::class);

            return new ElasticSearchEngine($client);
        });
    }
}
