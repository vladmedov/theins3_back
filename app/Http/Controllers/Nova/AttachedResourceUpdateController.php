<?php

namespace App\Http\Controllers\Nova;

use App\Http\Controllers\Nova\Concerns\AppliesTrafficCopTolerance;
use Illuminate\Support\Carbon;
use Laravel\Nova\Http\Controllers\AttachedResourceUpdateController as NovaAttachedResourceUpdateController;
use Laravel\Nova\Http\Requests\NovaRequest;

class AttachedResourceUpdateController extends NovaAttachedResourceUpdateController
{
    use AppliesTrafficCopTolerance;

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $model
     */
    protected function modelHasBeenUpdatedSinceRetrieval(NovaRequest $request, $model): bool
    {
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
