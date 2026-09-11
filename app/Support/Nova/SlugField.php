<?php

namespace App\Support\Nova;

use Illuminate\Validation\Rule;
use Laravel\Nova\Fields\Slug;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Shared Nova slug field: format (no spaces), per-language uniqueness, help text.
 */
final class SlugField
{
    public const FORMAT_REGEX = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * @return Slug|Text
     */
    public static function make(
        string $table,
        ?string $from = null,
        int $max = 255,
        bool $required = true,
        string $name = 'Slug',
        bool $asText = false,
    ) {
        // Nova Slug on update locks the field behind "Customize" (RU: «Изменить»).
        // That UX is fragile with FormActionBar and often leaves the control always
        // editable while the button does nothing — use Slug only on create (auto-from).
        $request = app(NovaRequest::class);
        $useSlugComponent = ! $asText
            && $from !== null
            && $request->isCreateOrAttachRequest();

        $field = $useSlugComponent
            ? Slug::make($name, 'slug')->from($from)
            : Text::make($name, 'slug');

        $formatRule = 'regex:'.self::FORMAT_REGEX;

        return $field
            ->sortable()
            ->help(__('Slug is the short URL part of the page. Use only lowercase Latin letters, numbers and hyphens. Spaces are not allowed.'))
            ->rules(function (NovaRequest $request) use ($table, $required, $max, $formatRule) {
                return array_values(array_filter([
                    $required ? 'required' : 'nullable',
                    "max:{$max}",
                    $formatRule,
                    self::uniqueRule($request, $table),
                ]));
            });
    }

    private static function uniqueRule(NovaRequest $request, string $table): \Illuminate\Validation\Rules\Unique
    {
        $languageCode = (string) (
            $request->input('language_code')
            ?: app()->getLocale()
        );

        $rule = Rule::unique($table, 'slug')
            ->where(fn ($query) => $query->where('language_code', $languageCode));

        if (filled($request->resourceId)) {
            $rule->ignore($request->resourceId);
        }

        return $rule;
    }
}
