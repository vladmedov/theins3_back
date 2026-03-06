<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Scout\EngineManager;
use Elastic\Elasticsearch\Client;
use Matchish\ScoutElasticSearch\Engines\ElasticSearchEngine;
use Matchish\ScoutElasticSearch\ElasticSearchServiceProvider;

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

        // Backward-compatible alias: many installs use SCOUT_DRIVER=elasticsearch.
        // matchish registers the engine under ElasticSearchEngine::class.
        resolve(EngineManager::class)->extend('elasticsearch', function ($app) {
            $client = $app->make(Client::class);

            return new ElasticSearchEngine($client);
        });
    }
}
