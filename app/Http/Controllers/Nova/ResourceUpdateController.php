<?php

namespace App\Http\Controllers\Nova;

use App\Http\Controllers\Nova\Concerns\AppendsServerUpdatedAt;
use App\Http\Controllers\Nova\Concerns\AppliesTrafficCopTolerance;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Laravel\Nova\Http\Controllers\ResourceUpdateController as NovaResourceUpdateController;
use Laravel\Nova\Http\Requests\UpdateResourceRequest;

class ResourceUpdateController extends NovaResourceUpdateController
{
    use AppendsServerUpdatedAt;
    use AppliesTrafficCopTolerance;

    /**
     * Include the saved updated_at so the form can refresh Traffic Cop from the server clock.
     */
    public function __invoke(UpdateResourceRequest $request): JsonResponse
    {
        $response = parent::__invoke($request);

        return $this->withServerUpdatedAt($response, $request->findModelQuery()->first());
    }

    /**
     * Post edit lock is enforced in {@see \App\Nova\_Posts\Post::authorizeToUpdate} for {@see UpdateResourceRequest} only,
     * so the model is loaded once inside Nova’s update flow.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     */
    protected function modelHasBeenUpdatedSinceRetrieval(UpdateResourceRequest $request, $model): bool
    {
        $resource = $request->newResource();

        if ($resource::trafficCop($request) === false) {
            return false;
        }

        $column = $model->getUpdatedAtColumn();

        if (! ($model->usesTimestamps() && $model->{$column})) {
            return false;
        }

        if (! $request->input('_retrieved_at')) {
            return false;
        }

        $retrievedAt = Carbon::createFromTimestamp($request->input('_retrieved_at'));

        return $model->{$column}->gt($this->trafficCopThresholdFromRetrievedAt($retrievedAt));
    }
}
