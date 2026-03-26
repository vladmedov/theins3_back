<?php

namespace App\Http\Controllers\Nova;

use App\Http\Controllers\Nova\Concerns\AppliesTrafficCopTolerance;
use App\Nova\_Posts\Post as PostNovaResource;
use App\Services\Nova\PostEditLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Laravel\Nova\Http\Controllers\ResourceUpdateController as NovaResourceUpdateController;
use Laravel\Nova\Http\Requests\UpdateResourceRequest;

class ResourceUpdateController extends NovaResourceUpdateController
{
    use AppliesTrafficCopTolerance;

    public function __invoke(UpdateResourceRequest $request): JsonResponse
    {
        $model = $request->findModelQuery()->lockForUpdate()->firstOrFail();
        $resource = $request->newResourceWith($model);

        if ($resource instanceof PostNovaResource) {
            $postKey = PostEditLockService::makePostKey($resource::uriKey(), (string) $model->getKey());
            app(PostEditLockService::class)->assertCanEditOrFail($postKey, $request->user());
        }

        return parent::__invoke($request);
    }

    /**
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
