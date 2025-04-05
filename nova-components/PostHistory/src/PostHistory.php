<?php

namespace Medov\PostHistory;

use Laravel\Nova\ResourceTool;

class PostHistory extends ResourceTool
{
    /**
     * Get the displayable name of the resource tool.
     */
    public function name(): string
    {
        return 'Post History';
    }

    /**
     * Get the component name for the resource tool.
     */
    public function component(): string
    {
        return 'post-history';
    }
}
