<?php

namespace App\Nova\Metrics;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;
use Laravel\Nova\Nova;

use App\Nova\Metrics\Traits\PostFilterTrait; 

use App\Enums\PostTypes;

class NewPosts extends Value
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
    
    public function calculate(NovaRequest $request): ValueResult
    {
        return $this->count(
            $request,
            $this->postFilter($this->_postType),
            'type',
            'published_at',
        );
    }

    public function ranges(): array
    {
        return [
            30 => Nova::__('30 Days'),
            60 => Nova::__('60 Days'),
            365 => Nova::__('365 Days'),
        ];
    }

    public function name()
    {
        return $this->_title;
    }

    public function uriKey(): string
    {
        return 'new-posts-' . strtolower($this->_postType);
    }
}