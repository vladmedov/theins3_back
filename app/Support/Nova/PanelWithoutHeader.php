<?php

namespace App\Support\Nova;

use Laravel\Nova\Panel;

/**
 * Panel without a visible header/title.
 * Use instead of Panel::make() when you need a panel wrapper without the heading.
 */
class PanelWithoutHeader extends Panel
{
    private const NAME_PREFIX = '_pnl_';

    /**
     * Create a new panel with no visible header.
     * Uses a unique placeholder name so Nova renders the panel, header is hidden via JS.
     *
     * @param  array<int, \Laravel\Nova\Fields\Field|\Laravel\Nova\Panel>  $fields
     * @param  string  $name  Unique identifier (e.g. 'general3') — required, must differ for each panel
     */
    public static function make(...$arguments)
    {
        $fields = $arguments[0] ?? [];
        $name = $arguments[1] ?? uniqid('panel_', true);
        return parent::make(self::NAME_PREFIX . $name, $fields)->withMeta(['hideHeader' => true]);
    }
}
