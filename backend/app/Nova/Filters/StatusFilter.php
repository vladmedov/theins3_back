<?php

namespace App\Nova\Filters;

use Illuminate\Http\Request;
use Laravel\Nova\Filters\Filter;

class StatusFilter extends Filter
{
    public $name = 'Статус';

    public function apply(Request $request, $query, $value)
    {
        return $query->where('status', $value);
    }

    public function options(Request $request)
    {
        return [
            'Черновик' => 'draft',
            'Опубликовано' => 'published',
        ];
    }
}
