<?php

namespace App\Services;

use Elastic\Elasticsearch\Client;
use Illuminate\Database\Eloquent\Model;
use Matchish\ScoutElasticSearch\ElasticSearch\DefaultAlias;
use Matchish\ScoutElasticSearch\ElasticSearch\FilteredAlias;
use Matchish\ScoutElasticSearch\ElasticSearch\Index;
use Matchish\ScoutElasticSearch\ElasticSearch\Params\Indices\Create;
use Matchish\ScoutElasticSearch\ElasticSearch\WriteAlias;
use Matchish\ScoutElasticSearch\Searchable\DefaultImportSourceFactory;

class SearchIndexManager
{
    public function __construct(
        private readonly Client $client,
    ) {
    }

    public function ensureWriteTarget(Model $model): void
    {
        $indexName = $model->searchableAs();

        if ($this->aliasExists($indexName) || $this->indexExists($indexName)) {
            return;
        }

        $source = DefaultImportSourceFactory::from($model::class);
        $index = Index::fromSource($source);

        $index->addAlias(
            new FilteredAlias(
                new WriteAlias(new DefaultAlias($source->searchableAs())),
                $index
            )
        );

        $params = new Create($index->name(), $index->config());
        $this->client->indices()->create($params->toArray());
    }

    public function aliasExists(string $name): bool
    {
        return $this->client->indices()->existsAlias(['name' => $name])->asBool();
    }

    public function indexExists(string $name): bool
    {
        return $this->client->indices()->exists(['index' => $name])->asBool();
    }

    public function deleteIndex(string $name): void
    {
        if (! $this->indexExists($name)) {
            return;
        }

        $this->client->indices()->delete(['index' => $name]);
    }
}
