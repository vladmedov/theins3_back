<?php

namespace App\Nova\Metrics;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;
use Laravel\Nova\Nova;

use App\Nova\Metrics\Traits\PostFilterTrait; 

use App\Enums\PostTypes;

class TotalViews extends Value
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
            PostTypes::ARTICLE => __('Article views'),
            PostTypes::NEWS => __('News views'),
            PostTypes::OPINION => __('Opinion views'),
            PostTypes::ONLINE => __('Online views'),
            default => __('Total views')
        };
    }
    
    public function calculate(NovaRequest $request): ValueResult
    {
        $range = $request->get('range', 30); // По умолчанию 30 дней
        
        $query = $this->postFilter($this->_postType);
        
        // Фильтруем по диапазону дат
        $query->where('published_at', '>=', now()->subDays($range));

        // Подсчитываем сумму просмотров
        $totalViews = $query->sum('views_count');
        
        // Подсчитываем сумму просмотров за предыдущий период для сравнения
        $previousQuery = $this->postFilter($this->_postType);
        $previousQuery->whereBetween('published_at', [
            now()->subDays($range * 2),
            now()->subDays($range)
        ]);
        $previousViews = $previousQuery->sum('views_count');

        return $this->result($totalViews)->previous($previousViews);
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
        return 'total-views-' . strtolower($this->_postType);
    }
}

