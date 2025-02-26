<?php

namespace App\Nova\Dashboards;

use Laravel\Nova\Dashboard;

use App\Nova\Metrics\NewPosts;
use App\Nova\Metrics\PostsPerDay;
use App\Nova\Metrics\ViewsPerDay;
use App\Nova\Metrics\AverageViewsPerDay;

use Medov\TopUsersCard\TopUsersCard;

use App\Enums\PostTypes;

class ContentDashboard extends Dashboard
{
    /**
     * Get the cards for the dashboard.
     *
     * @return array<int, \Laravel\Nova\Card>
     */
    public function cards(): array
    {
        return [
            new NewPosts(PostTypes::ARTICLE),
            new NewPosts(PostTypes::NEWS),
            new NewPosts(PostTypes::OPINION),
            new NewPosts(PostTypes::ONLINE),
            new PostsPerDay(PostTypes::ARTICLE),
            new PostsPerDay(PostTypes::NEWS),
            new PostsPerDay(PostTypes::OPINION),
            new PostsPerDay(PostTypes::ONLINE),
            new TopUsersCard(PostTypes::ARTICLE),
            new TopUsersCard(PostTypes::NEWS),
            new TopUsersCard(PostTypes::OPINION),
            new TopUsersCard(PostTypes::ONLINE),
            ViewsPerDay::make(PostTypes::ARTICLE)->help('Просмотры агрегируются по постам, а не по датам'),
            new ViewsPerDay(PostTypes::NEWS),
            new ViewsPerDay(PostTypes::OPINION),
            new ViewsPerDay(PostTypes::ONLINE),
            new AverageViewsPerDay(PostTypes::ARTICLE),
            new AverageViewsPerDay(PostTypes::NEWS),
            new AverageViewsPerDay(PostTypes::OPINION),
            new AverageViewsPerDay(PostTypes::ONLINE),
        ];
    }

    public function uriKey(): string
    {
        return 'content-dashboard';
    }

    public function name(): string
    {
        return 'Content dashboard';
    }
}
