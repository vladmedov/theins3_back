<?php

namespace App\Nova\Filters;

use Illuminate\Http\Request;
use Laravel\Nova\Filters\Filter;

use App\Models\Category;
use App\Enums\CategoryTypes;

class CategoryFilter extends Filter
{
    public $name = 'Рубрика';

    public function apply(Request $request, $query, $value)
    {
        return $query
            ->where('category_id', $value);
    }

    public function options(Request $request)
    {
        return Category
            ::where('language_code', app()->getLocale())
            ->where('type', CategoryTypes::DEFAULT)
            ->pluck('id', 'title')
            ->toArray();
    }
}
