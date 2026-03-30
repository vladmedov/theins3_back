<?php

namespace App\Nova\Dashboards;

use Laravel\Nova\Dashboard;

use App\Nova\Metrics\PostsPerDay;
use App\Nova\Metrics\ViewsPerDay;

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
            new PostsPerDay(PostTypes::NEWS),
            new ViewsPerDay(PostTypes::NEWS),
            new TopUsersCard(PostTypes::NEWS),
            TopUsersCard::articleViews(),

            new PostsPerDay(PostTypes::ARTICLE),
            new ViewsPerDay(PostTypes::ARTICLE),

            new PostsPerDay(PostTypes::OPINION),
            new ViewsPerDay(PostTypes::OPINION),

            new PostsPerDay(PostTypes::ONLINE),
            new ViewsPerDay(PostTypes::ONLINE),
            
            // new TopUsersCard(PostTypes::ARTICLE),
            // new TopUsersCard(PostTypes::NEWS),
            // new TopUsersCard(PostTypes::OPINION),
            // new TopUsersCard(PostTypes::ONLINE),
        ];
    }

    public function uriKey(): string
    {
        return 'content-dashboard';
    }

    public function name(): string
    {
        return 'Statistics';
    }
}
