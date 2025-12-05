<?php

namespace App\Nova\Metrics;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Metrics\TrendResult;

use App\Nova\Metrics\Traits\PostFilterTrait; 

use App\Enums\PostTypes;

class PostsPerDay extends Trend
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
            PostTypes::ARTICLE => __('Articles count'),
            PostTypes::NEWS => __('News count'),
            PostTypes::OPINION => __('Opinions count'),
            PostTypes::ONLINE => __('Onlines count'),
            default => __('Posts count')
        };
    }

    public function calculate(NovaRequest $request): TrendResult
    {
        $range = $request->get('range', 30);

        switch ($range) {
            case 12:
                return $this->countByMonths(
                    $request,
                    $this->postFilter($this->_postType),
                    'published_at'
                );

            default:
                return $this->countByDays(
                    $request,
                    $this->postFilter($this->_postType),
                    'published_at'
                );
        }
    }

    public function ranges(): array
    {
        return [
            30 => __('Today'),
            12 => __('This month'),
        ];
    }

    public function name()
    {
        return $this->_title;
    }
    
    public function uriKey(): string
    {
        return 'posts-per-day-' . strtolower($this->_postType);
    }
}