<?php

namespace App\Nova\Metrics;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Metrics\TrendResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

use App\Nova\Metrics\Traits\PostFilterTrait; 

use App\Enums\PostTypes;
use App\Models\Post;

class PostsPerDay extends Trend
{
    use PostFilterTrait;

    public $width = '1/4';

    private const CONTEXT_DASHBOARD = 'dashboard';
    private const CONTEXT_RESOURCE = 'resource';
    private const CACHE_TTL_SECONDS = 900;

    private $_title = null;
    private $_postType = null;
    private string $context = self::CONTEXT_DASHBOARD;

    public function __construct($postType)
    {
        parent::__construct();

        $this->_postType = $postType;
        $this->defaultRange((string) $this->resolveDefaultRangeValue());
        $this->_title = match ($postType) {
            PostTypes::ARTICLE => __('nova_metrics.posts.articles_count'),
            PostTypes::NEWS => __('nova_metrics.posts.news_count'),
            PostTypes::OPINION => __('nova_metrics.posts.opinions_count'),
            PostTypes::ONLINE => __('nova_metrics.posts.onlines_count'),
            default => __('nova_metrics.posts.posts_count')
        };
    }

    public function forResourcePage(): self
    {
        $this->context = self::CONTEXT_RESOURCE;
        $this->defaultRange((string) $this->resolveDefaultRangeValue());

        return $this;
    }

    public function calculate(NovaRequest $request): TrendResult
    {
        $range = (int) $request->get('range', $this->resolveDefaultRangeValue());
        $cacheKey = sprintf(
            'nova:metric:posts_per_day:%s:%s:%s:%d',
            app()->getLocale(),
            (string) $this->_postType,
            $this->context,
            $range
        );

        return Cache::remember(
            $cacheKey,
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function () use ($request, $range) {
                if ($this->context === self::CONTEXT_DASHBOARD && $this->_postType === PostTypes::ONLINE) {
                    return $this->countOnlineByYears();
                }

                $query = $this->postFilter($this->_postType)
                    ->where('status', Post::STATUS_PUBLISHED);

                $result = match ($this->resolveUnitByRange($range)) {
                    'months' => $this->countByMonths($request, $query, 'published_at')->showSumValue(),
                    'weeks' => $this->countByWeeks($request, $query, 'published_at')->showSumValue(),
                    default => $this->countByDays($request, $query, 'published_at')->showSumValue(),
                };

                if ($this->context === self::CONTEXT_RESOURCE) {
                    return $result->result($this->resolveTodayCount());
                }

                return $result;
            }
        );
    }

    public function ranges(): array
    {
        if ($this->context === self::CONTEXT_RESOURCE) {
            return match ($this->_postType) {
                PostTypes::NEWS => [
                    30 => __('nova_metrics.ranges.month'),
                ],
                PostTypes::ARTICLE => [
                    26 => __('nova_metrics.ranges.half_year'),
                ],
                PostTypes::OPINION => [
                    26 => __('nova_metrics.ranges.half_year'),
                ],
                PostTypes::ONLINE => [
                    60 => __('nova_metrics.ranges.five_years'),
                ],
                default => [
                    30 => __('nova_metrics.ranges.month'),
                ],
            };
        }

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
                12 => __('nova_metrics.ranges.this_month'),
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
        if ($this->context === self::CONTEXT_RESOURCE) {
            return match ($this->_postType) {
                PostTypes::NEWS => 30,
                PostTypes::ARTICLE => 26,
                PostTypes::OPINION => 26,
                PostTypes::ONLINE => 60,
                default => 30,
            };
        }

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
        if ($this->context === self::CONTEXT_RESOURCE) {
            return match ($this->_postType) {
                PostTypes::ARTICLE, PostTypes::OPINION => 'weeks',
                PostTypes::ONLINE => 'months',
                default => 'days',
            };
        }

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

        $baseQuery = $this->postFilter($this->_postType)
            ->where('status', Post::STATUS_PUBLISHED);
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

    private function resolveTodayCount(): int
    {
        $todayStart = Carbon::now()->startOfDay();
        $todayEnd = Carbon::now()->endOfDay();

        return (int) $this->postFilter($this->_postType)
            ->where('status', Post::STATUS_PUBLISHED)
            ->whereBetween('published_at', [$todayStart, $todayEnd])
            ->count();
    }
}