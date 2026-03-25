<?php

namespace App\Nova;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource as NovaResource;
use Laravel\Scout\Builder as ScoutBuilder;

abstract class Resource extends NovaResource
{
    /**
     * Language for field defaults and relatable queries: the record’s `language_code` when editing, else app locale.
     */
    protected function effectiveResourceLanguageCode(): string
    {
        return $this->exists && $this->language_code !== null && $this->language_code !== ''
            ? (string) $this->language_code
            : app()->getLocale();
    }

    /**
     * For static query hooks when the request targets a resource row (e.g. relatable from another resource’s form).
     */
    public static function resolveResourceLanguageCodeForRequest(NovaRequest $request): string
    {
        if (filled($request->resourceId)) {
            $model = rescue(static fn () => $request->findModel(), null, false);
            if ($model && isset($model->language_code) && $model->language_code !== '') {
                return (string) $model->language_code;
            }
        }

        return app()->getLocale();
    }

    /**
     * Build an "index" query for the given resource.
     */
    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query;
    }

    /**
     * Build a Scout search query for the given resource.
     */
    public static function scoutQuery(NovaRequest $request, ScoutBuilder $query): ScoutBuilder
    {
        return $query;
    }

    /**
     * Build a "detail" query for the given resource.
     */
    public static function detailQuery(NovaRequest $request, Builder $query): Builder
    {
        return parent::detailQuery($request, $query);
    }

    /**
     * Build a "relatable" query for the given resource.
     *
     * This query determines which instances of the model may be attached to other resources.
     */
    public static function relatableQuery(NovaRequest $request, Builder $query): Builder
    {
        return parent::relatableQuery($request, $query);
    }
}
