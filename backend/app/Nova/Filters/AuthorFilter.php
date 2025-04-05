<?php

namespace App\Nova\Filters;

use Illuminate\Http\Request;
use Laravel\Nova\Filters\Filter;
use App\Models\Author;

class AuthorFilter extends Filter
{
    public $name = 'Авторы';

    public function apply(Request $request, $query, $value)
    {
        return $query->whereHas('authors', function ($q) use ($value) {
            $q->where('user_id', $value);
        });
    }

    public function options(Request $request)
    {
        return Author::select('id', 'first_name', 'last_name')
            ->where('language_code', app()->getLocale())
            ->get()
            ->mapWithKeys(function ($author) {
                return [$author->first_name . ' ' . $author->last_name => $author->id];
            })
            ->toArray();
    }
}
