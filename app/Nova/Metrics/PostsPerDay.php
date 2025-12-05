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
            PostTypes::ARTICLE => __('Articles per day'),
            PostTypes::NEWS => __('News per day'),
            PostTypes::OPINION => __('Opinions per day'),
            PostTypes::ONLINE => __('Onlines per day'),
            default => __('Posts per day')
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
            30 => __('30 Days'),
            12 => __('12 Months'),
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