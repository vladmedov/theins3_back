<?php

namespace App\Nova\Filters;

use App\Models\Author;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class AuthorFilter extends Filter
{
    public $name = 'Авторы';

    public $searchable = true;

    public function apply(NovaRequest $request, Builder $query, mixed $value)
    {
        return $query->whereHas('authors', function ($q) use ($value) {
            $q->where('authors.id', $value);
        });
    }

    public function options(NovaRequest $request)
    {
        return Author::select('id', 'first_name', 'last_name')
            ->where('language_code', app()->getLocale())
            ->orderBy('last_name')
            ->get()
            ->mapWithKeys(function ($author) {
                return [trim($author->first_name . ' ' . $author->last_name) => $author->id];
            })
            ->toArray();
    }
}
