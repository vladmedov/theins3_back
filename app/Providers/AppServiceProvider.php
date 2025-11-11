<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Scout\EngineManager;
use Elastic\Elasticsearch\ClientBuilder;
use Matchish\ScoutElasticSearch\Engines\ElasticSearchEngine;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Регистрируем PSR-18 HTTP клиент для Elasticsearch
        $this->app->bind(\Psr\Http\Client\ClientInterface::class, function () {
            return new \GuzzleHttp\Client();
        });

        // Регистрируем Elasticsearch клиент
        $this->app->singleton(\Elastic\Elasticsearch\Client::class, function ($app) {
            $httpClient = new \GuzzleHttp\Client();
            $hosts = config('scout.elasticsearch.hosts', ['elasticsearch:9200']);
            
            $clientBuilder = ClientBuilder::create();
            $clientBuilder->setHosts($hosts);
            $clientBuilder->setHttpClient($httpClient);
            
            return $clientBuilder->build();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        // Расширяем Scout для поддержки Elasticsearch
        resolve(EngineManager::class)->extend('elasticsearch', function ($app) {
            $client = $app->make(\Elastic\Elasticsearch\Client::class);

            return new ElasticSearchEngine(
                $client,
                config('scout.elasticsearch.update_mapping', true)
            );
        });
    }
}
