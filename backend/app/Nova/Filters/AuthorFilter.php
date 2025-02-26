<?php

namespace App\Nova\Filters;

use Illuminate\Http\Request;
use Laravel\Nova\Filters\Filter;
use App\Models\User;

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
        return User::select('id', 'ru_first_name', 'ru_second_name')
            ->get()
            ->mapWithKeys(function ($user) {
                return [$user->ru_first_name . ' ' . $user->ru_second_name => $user->id];
            })
            ->toArray();
    }
}
