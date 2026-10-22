<?php

namespace App\Http\Controllers\Nova\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

trait AppendsServerUpdatedAt
{
    /**
     * Unix seconds of the model's updated_at, so the client can stamp Traffic Cop
     * without using the browser clock.
     */
    protected function withServerUpdatedAt(JsonResponse $response, mixed $model): JsonResponse
    {
        if (! $model instanceof Model) {
            return $response;
        }

        $column = $model->getUpdatedAtColumn();
        $updatedAt = $column ? $model->{$column} : null;

        if (! $column || ! $model->usesTimestamps() || ! $updatedAt instanceof \DateTimeInterface) {
            return $response;
        }

        $payload = json_decode($response->getContent());

        if (! is_object($payload)) {
            return $response;
        }

        $payload->updated_at = $updatedAt->getTimestamp();
        $response->setData($payload);

        return $response;
    }
}
