<?php

namespace App\Support\Nova;

use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Resource;

/**
 * Панель без шапки: «ТИП: заголовок» + дополнительные поля (например Hidden).
 * Стили: .nova-post-edit-page-title в public/css/custom-nova.css.
 */
final class PageTitle
{
    /**
     * @param  array<int, \Laravel\Nova\Fields\Field>  $fields
     * @param  (callable(Resource): string)|null  $resolveHeadline  если null — заголовок из атрибута title модели
     */
    public static function make(
        Resource $resource,
        string $panelName,
        array $fields = [],
        ?callable $resolveHeadline = null,
    ): PanelWithoutHeader {
        $type = mb_strtoupper((string) $resource::singularLabel(), 'UTF-8');
        $headline = $resolveHeadline !== null
            ? $resolveHeadline($resource)
            : self::defaultHeadlineFromModelTitle($resource);

        $panelFields = [
            Heading::make(self::headingHtml($type, $headline))
                ->asHtml()
                ->onlyOnForms(),
            ...$fields,
        ];

        return PanelWithoutHeader::make($panelFields, $panelName);
    }

    /**
     * Заголовок из связи (BelongsTo и т.п.): related->title.
     *
     * @param  array<int, \Laravel\Nova\Fields\Field>  $fields
     */
    public static function makeForRelationTitle(
        Resource $resource,
        string $panelName,
        string $relation,
        string $relatedTitleKey = 'title',
        array $fields = [],
    ): PanelWithoutHeader {
        return self::make(
            $resource,
            $panelName,
            $fields,
            function (Resource $r) use ($relation, $relatedTitleKey) {
                if (! $r->exists) {
                    return __('Publication form new');
                }
                $related = $r->resource->{$relation} ?? null;
                $headline = $related !== null
                    ? trim((string) data_get($related, $relatedTitleKey, ''))
                    : '';

                return $headline !== ''
                    ? $headline
                    : __('Publication form no title');
            }
        );
    }

    public static function headingHtml(string $typeUpper, string $headline): string
    {
        return '<span class="nova-post-edit-page-title">'
            .'<span class="nova-post-edit-page-title__type">'.e($typeUpper).'</span>'
            .'<span class="nova-post-edit-page-title__sep">: </span>'
            .'<span class="nova-post-edit-page-title__headline">'.e($headline).'</span>'
            .'</span>';
    }

    public static function defaultHeadlineFromModelTitle(Resource $resource): string
    {
        if (! $resource->exists) {
            return __('Publication form new');
        }
        $headline = trim((string) ($resource->resource->title ?? ''));

        return $headline !== ''
            ? $headline
            : __('Publication form no title');
    }
}
