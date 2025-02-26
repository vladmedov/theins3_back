<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\Filter;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Laravel\Nova\Filters\DateFilter;
use Laravel\Nova\Http\Requests\NovaRequest;

class DatePublishedFromFilter extends DateFilter
{
    public $name = 'Дата после';

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('published_at', '>=', Carbon::parse($value));
    }
}
