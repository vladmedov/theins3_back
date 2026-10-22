<?php

namespace App\Http\Controllers\Nova;

use App\Http\Controllers\Nova\Concerns\AppendsServerUpdatedAt;
use Illuminate\Http\JsonResponse;
use Laravel\Nova\Http\Controllers\UpdateFieldController as NovaUpdateFieldController;
use Laravel\Nova\Http\Requests\ResourceUpdateOrUpdateAttachedRequest;

class UpdateFieldController extends NovaUpdateFieldController
{
    use AppendsServerUpdatedAt;

    /**
     * List the update fields for the given resource.
     */
    public function __invoke(ResourceUpdateOrUpdateAttachedRequest $request): JsonResponse
    {
        $response = parent::__invoke($request);

        return $this->withServerUpdatedAt($response, $request->findModelQuery()->first());
    }
}
