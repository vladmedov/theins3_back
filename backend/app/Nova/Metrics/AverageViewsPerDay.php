<?php

namespace App\Nova\Metrics;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Metrics\TrendResult;

use App\Nova\Metrics\Traits\PostFilterTrait; 

use App\Enums\PostTypes;

class AverageViewsPerDay extends Trend
{
    use PostFilterTrait;

    public $width = '1/4';

    private $_title = null;
    private $_postType = null;

    public function __construct($postType)
    {
        parent::__construct();

        $this->_postType = $postType;
        $this->_title = match ($postType) {
            PostTypes::ARTICLE => __('Avg. article views per day'),
            PostTypes::NEWS => __('Avg. news views per day'),
            PostTypes::OPINION => __('Avg. opinion views per day'),
            PostTypes::ONLINE => __('Avg. online views per day'),
            default => __('Avg. post views per day')
        };
    }

    public function calculate(NovaRequest $request): TrendResult
    {
        return $this->averageByDays(
            $request,
            $this->postFilter($this->_postType),
            'views_count',
            'published_at'
        );
    }

    public function ranges(): array
    {
        return [
            30 => __('30 Days'),
        ];
    }

    public function name()
    {
        return $this->_title;
    }
    
    public function uriKey(): string
    {
        return 'average-views-per-day-' . strtolower($this->_postType);
    }
}