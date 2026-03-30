<?php

namespace App\Nova\Metrics;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Metrics\TrendResult;
use Illuminate\Support\Carbon;

use App\Nova\Metrics\Traits\PostFilterTrait; 

use App\Enums\PostTypes;
use App\Models\Post;

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
        $this->defaultRange((string) $this->resolveDefaultRangeValue());
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
        if ($this->_postType === PostTypes::ONLINE) {
            return $this->countOnlineByYears();
        }

        $range = (int) $request->get('range', $this->resolveDefaultRangeValue());
        $query = $this->postFilter($this->_postType)
            ->where('status', Post::STATUS_PUBLISHED);

        return match ($this->resolveUnitByRange($range)) {
            'months' => $this->countByMonths($request, $query, 'published_at')->showSumValue(),
            'weeks' => $this->countByWeeks($request, $query, 'published_at')->showSumValue(),
            default => $this->countByDays($request, $query, 'published_at')->showSumValue(),
        };
    }

    public function ranges(): array
    {
        return match ($this->_postType) {
            PostTypes::NEWS => [
                7 => __('Week'),
            ],
            PostTypes::ARTICLE => [
                8 => __('Month'),
            ],
            PostTypes::OPINION => [
                6 => __('Half-year'),
            ],
            PostTypes::ONLINE => [
                5 => __('5 Years'),
            ],
            default => [
                30 => __('Today'),
                12 => __('This month'),
            ],
        };
    }

    public function name()
    {
        return $this->_title;
    }
    
    public function uriKey(): string
    {
        return 'posts-per-day-' . strtolower($this->_postType);
    }

    private function resolveDefaultRangeValue(): int
    {
        return match ($this->_postType) {
            PostTypes::NEWS => 7,
            PostTypes::ONLINE => 5,
            PostTypes::ARTICLE => 8,
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
            default => match ($range) {
                12 => 'months',
                default => 'days',
            },
        };
    }

    private function countOnlineByYears(): TrendResult
    {
        $currentYear = Carbon::now()->year;
        $startYear = $currentYear - 4;

        $baseQuery = $this->postFilter($this->_postType);
        $trend = [];

        foreach (range($startYear, $currentYear) as $year) {
            $from = Carbon::create($year, 1, 1)->startOfDay();
            $to = Carbon::create($year, 12, 31)->endOfDay();
            $trend[(string) $year] = (int) (clone $baseQuery)
                ->whereBetween('published_at', [$from, $to])
                ->count();
        }

        return $this->result(0)->trend($trend)->showSumValue();
    }
}