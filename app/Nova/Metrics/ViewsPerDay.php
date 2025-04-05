<?php

namespace App\Nova\Metrics;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Metrics\TrendResult;

use App\Nova\Metrics\Traits\PostFilterTrait; 

use App\Enums\PostTypes;

class ViewsPerDay extends Trend
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
            PostTypes::ARTICLE => __('Article views per day'),
            PostTypes::NEWS => __('News views per day'),
            PostTypes::OPINION => __('Opinion views per day'),
            PostTypes::ONLINE => __('Online views per day'),
            default => __('Post views per day')
        };
    }

    public function calculate(NovaRequest $request): TrendResult
    {
        return $this->sumByDays(
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
        return 'views-per-day-' . strtolower($this->_postType);
    }
}