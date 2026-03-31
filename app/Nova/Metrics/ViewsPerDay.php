<?php

namespace App\Nova\Metrics;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Metrics\TrendResult;
use Illuminate\Support\Carbon;

use App\Nova\Metrics\Traits\PostFilterTrait; 

use App\Enums\PostTypes;
use App\Models\Post;

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
        $this->defaultRange((string) $this->resolveDefaultRangeValue());
        $this->_title = match ($postType) {
            PostTypes::ARTICLE => __('nova_metrics.views.article_views'),
            PostTypes::NEWS => __('nova_metrics.views.news_views'),
            PostTypes::OPINION => __('nova_metrics.views.opinion_views'),
            PostTypes::ONLINE => __('nova_metrics.views.online_views'),
            default => __('nova_metrics.views.post_views')
        };
    }

    public function calculate(NovaRequest $request): TrendResult
    {
        if ($this->_postType === PostTypes::ONLINE) {
            return $this->sumOnlineViewsByYears();
        }

        $range = (int) $request->get('range', $this->resolveDefaultRangeValue());
        $query = $this->postFilter($this->_postType)
            ->where('status', Post::STATUS_PUBLISHED);

        return match ($this->resolveUnitByRange($range)) {
            'months' => $this->sumByMonths($request, $query, 'views_count', 'published_at')->showSumValue(),
            'weeks' => $this->sumByWeeks($request, $query, 'views_count', 'published_at')->showSumValue(),
            default => $this->sumByDays($request, $query, 'views_count', 'published_at')->showSumValue(),
        };
    }

    public function ranges(): array
    {
        return match ($this->_postType) {
            PostTypes::NEWS => [
                7 => __('nova_metrics.ranges.week'),
            ],
            PostTypes::ARTICLE => [
                30 => __('nova_metrics.ranges.month'),
            ],
            PostTypes::OPINION => [
                6 => __('nova_metrics.ranges.half_year'),
            ],
            PostTypes::ONLINE => [
                5 => __('nova_metrics.ranges.five_years'),
            ],
            default => [
                30 => __('nova_metrics.ranges.today'),
            ],
        };
    }

    public function name()
    {
        return $this->_title;
    }
    
    public function uriKey(): string
    {
        return 'views-per-day-' . strtolower($this->_postType);
    }

    private function resolveDefaultRangeValue(): int
    {
        return match ($this->_postType) {
            PostTypes::NEWS => 7,
            PostTypes::ONLINE => 5,
            PostTypes::ARTICLE => 30,
            PostTypes::OPINION => 6,
            default => 30,
        };
    }

    private function resolveUnitByRange(int $range): string
    {
        return match ($this->_postType) {
            PostTypes::NEWS => match ($range) {
                default => 'days',
            },
            PostTypes::ARTICLE, PostTypes::OPINION => match ($range) {
                6 => 'months',
                default => 'days',
            },
            PostTypes::ONLINE => 'days',
            default => 'days',
        };
    }

    private function sumOnlineViewsByYears(): TrendResult
    {
        $currentYear = Carbon::now()->year;
        $startYear = $currentYear - 4;

        $baseQuery = $this->postFilter($this->_postType);
        $trend = [];

        foreach (range($startYear, $currentYear) as $year) {
            $from = Carbon::create($year, 1, 1)->startOfDay();
            $to = Carbon::create($year, 12, 31)->endOfDay();
            $trend[(string) $year] = (int) ((clone $baseQuery)
                ->whereBetween('published_at', [$from, $to])
                ->sum('views_count') ?? 0);
        }

        return $this->result(0)->trend($trend)->showSumValue();
    }
}