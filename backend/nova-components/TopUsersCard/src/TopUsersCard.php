<?php

namespace Medov\TopUsersCard;

use Laravel\Nova\Card;
use App\Models\User;
use App\Enums\PostTypes;

class TopUsersCard extends Card
{
    public $width = '1/4';

    public function __construct($postType)
    {
        parent::__construct();

        $title = match ($postType) {
            PostTypes::ARTICLE => __('Top Authors (Last 30 Days)'),
            PostTypes::NEWS => __('Top News Writers (Last 30 Days)'),
            PostTypes::OPINION => __('Top Columnists (Last 30 Days)'),
            PostTypes::ONLINE => __('Top Online Writers (Last 30 Days)'),
        };

        $topNewsWriters = User::getTopUsersByPeriod($postType, 10, 30);

        $this->withMeta([
            'topNewsWriters' => $topNewsWriters,
            'title' => $title
        ]);
    }

    public function component(): string
    {
        return 'top-users-card';
    }
}
