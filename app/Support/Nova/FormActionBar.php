<?php

namespace App\Support\Nova;

use Laravel\Nova\Fields\Heading;

class FormActionBar
{
    /**
     * Create a Nova field that renders FormActionBar in place within the fields array.
     * Use instead of Heading::make(FormActionBar::render(...)) when the bar must stay at its position.
     */
    public static function make(array $options = [], string $attribute = '_form_action_bar'): Heading
    {
        return Heading::make(self::render($options), $attribute)
            ->asHtml()
            ->fillUsing(fn () => null)
            ->onlyOnForms();
    }

    public static function render(array $options = []): string
    {
        $options = self::expandShorthandOptions($options);

        $defaultSaveAction = [
            'label' => __('Save'),
            'js' => self::defaultSaveJs(),
        ];

        if (array_key_exists('saveAction', $options)) {
            $saveAction = is_array($options['saveAction'])
                ? array_merge($defaultSaveAction, $options['saveAction'])
                : null;
        } else {
            $saveAction = $defaultSaveAction;
        }

        $secondaryAction = $options['secondaryAction'] ?? null;
        if ($secondaryAction) {
            $secondaryAction = array_merge([
                'label' => '',
                'js' => '',
                'variant' => 'neutral-link',
            ], $secondaryAction);
        }

        $stayAction = $options['stayAction'] ?? null;
        if ($stayAction) {
            $stayAction = array_merge([
                'label' => __('Save'),
                'js' => '',
                'variant' => 'neutral-link',
                'savingLabel' => __('form_action_bar.saving'),
                'originalStatus' => null,
            ], $stayAction);
        }

        $linkBlock = $options['linkBlock'] ?? null;
        if ($linkBlock) {
            $linkBlock = array_merge([
                'eyebrow' => __('form_action_bar.url'),
                'url' => null,
                'notice' => null,
                'copyable' => true,
                'copyTitle' => __('form_action_bar.copy_link'),
            ], $linkBlock);
        }

        $metaBlock = $options['metaBlock'] ?? null;
        if ($metaBlock) {
            $metaBlock = array_merge([
                'items' => [],
            ], $metaBlock);

            $metaBlock['items'] = collect($metaBlock['items'])
                ->map(function ($item) {
                    if (!empty($item['date']) && empty($item['value'])) {
                        $item['value'] = self::formatDate($item['date']);
                    }

                    return $item;
                })
                ->filter(fn ($item) => !empty($item['value']))
                ->values()
                ->all();
        }

        $autosave = $options['autosave'] ?? null;
        if ($autosave) {
            $autosave = array_merge([
                'enabled' => false,
                'statusLabel' => __('form_action_bar.autosave'),
                'idleLabel' => __('form_action_bar.autosave_idle'),
                'lastSavedLabel' => __('form_action_bar.last_saved_at'),
                'lastSavedDatePrefix' => __('form_action_bar.last_saved_date_prefix'),
                'updatedAtIso' => null,
            ], $autosave);

            if (!empty($autosave['updated_at']) && empty($autosave['updatedAtIso'])) {
                // UTC + ISO8601, чтобы в браузере однозначный момент времени (без «сдвига» при разном app.timezone)
                $autosave['updatedAtIso'] = $autosave['updated_at']->copy()->utc()->toIso8601String();
            }
        }

        $heading = $options['heading'] ?? null;
        $scrollNav = $options['scrollNav'] ?? null;
        if ($scrollNav) {
            $scrollNav = array_merge([
                'direction' => 'down',
                'label' => '↓',
                'title' => __('Scroll'),
            ], $scrollNav);
        }

        return view('nova.components.form-action-bar', [
            'heading' => $heading,
            'secondaryAction' => $secondaryAction,
            'stayAction' => $stayAction,
            'saveAction' => $saveAction,
            'linkBlock' => $linkBlock,
            'metaBlock' => $metaBlock,
            'autosave' => $autosave,
            'scrollNav' => $scrollNav,
        ])->render();
    }

    protected static function expandShorthandOptions(array $options): array
    {
        $expanded = $options;
        $tokens = collect($options)
            ->filter(fn ($value, $key) => is_int($key) && is_string($value))
            ->values()
            ->all();

        $usesShorthand = !empty($tokens)
            || array_key_exists('created_at', $options)
            || array_key_exists('updated_at', $options)
            || array_key_exists('url', $options)
            || array_key_exists('stay', $options)
            || array_key_exists('toggle_publish', $options);

        if (!$usesShorthand) {
            return $expanded;
        }

        if (!array_key_exists('saveAction', $expanded)) {
            $expanded['saveAction'] = in_array('save', $tokens, true) ? [] : null;
        }

        if (array_key_exists('stay', $options) && !array_key_exists('stayAction', $expanded)) {
            $expanded['stayAction'] = self::makeStayAction($options['stay']);
        }

        if (array_key_exists('toggle_publish', $options) && !array_key_exists('secondaryAction', $expanded)) {
            $expanded['secondaryAction'] = self::makeTogglePublishAction($options['toggle_publish']);
        }

        if (array_key_exists('url', $options) && !array_key_exists('linkBlock', $expanded)) {
            $expanded['linkBlock'] = self::makeLinkBlock($options['url']);
        }

        if (
            (array_key_exists('created_at', $options) || array_key_exists('updated_at', $options))
            && !array_key_exists('metaBlock', $expanded)
        ) {
            $expanded['metaBlock'] = [
                'items' => array_values(array_filter([
                    self::makeMetaItem('created_at', $options['created_at'] ?? null),
                    self::makeMetaItem('updated_at', $options['updated_at'] ?? null),
                ])),
            ];
        }

        if (array_key_exists('autosave', $options) && !array_key_exists('autosave', $expanded)) {
            $expanded['autosave'] = $options['autosave'];
        }

        return $expanded;
    }

    protected static function makeStayAction(mixed $config): ?array
    {
        if ($config === null || $config === false) {
            return null;
        }

        $config = is_array($config) ? $config : [];
        $exists = (bool) ($config['exists'] ?? false);
        $status = $config['status'] ?? null;

        return array_merge([
            'label' => $exists ? __('Save') : __('Create'),
            'js' => $exists ? self::saveWithoutReloadJs() : self::defaultSaveJs(),
            'variant' => 'primary',
            'savingLabel' => __('form_action_bar.saving'),
            'originalStatus' => $status,
        ], $config);
    }

    protected static function makeTogglePublishAction(mixed $config): ?array
    {
        if ($config === null || $config === false) {
            return null;
        }

        $config = is_array($config) ? $config : ['status' => $config];
        $status = $config['status'] ?? null;
        $isPublished = $status === 'published';

        return array_merge([
            'label' => $isPublished ? __('Unpublish') : __('Publish'),
            'js' => $isPublished ? self::unpublishJs() : self::publishJs(),
            'variant' => $isPublished ? 'danger-link' : 'success-link',
        ], $config);
    }

    protected static function makeLinkBlock(mixed $config): ?array
    {
        if (empty($config)) {
            return null;
        }

        return is_array($config)
            ? $config
            : ['url' => $config];
    }

    protected static function makeMetaItem(string $key, mixed $value): ?array
    {
        if (empty($value)) {
            return null;
        }

        return [
            'label' => __('form_action_bar.' . $key),
            'date' => $value,
        ];
    }

    protected static function formatDate($date): ?string
    {
        if (!$date) {
            return null;
        }

        $userTz = auth()->user()->timezone ?? config('app.timezone');

        if ($userTz && in_array($userTz, timezone_identifiers_list(), true)) {
            $date = $date->copy()->setTimezone($userTz);
        }

        return $date->format('d.m.Y H:i');
    }

    protected static function defaultSaveJs(): string
    {
        return "document.querySelector('button[dusk=create-button],button[dusk=update-button]')?.click()";
    }

    protected static function saveWithoutReloadJs(): string
    {
        return 'window.NovaCustomSave && window.NovaCustomSave.saveWithoutReload ? window.NovaCustomSave.saveWithoutReload(this) : null';
    }

    protected static function publishJs(): string
    {
        return self::statusChangeJs('published');
    }

    protected static function unpublishJs(): string
    {
        return self::statusChangeJs('draft');
    }

    protected static function statusChangeJs(string $status): string
    {
        return "(function(){" .
            "var s=Array.from(document.querySelectorAll('select')).find(function(el){return Array.from(el.options).some(function(o){return o.value==='published'});});" .
            "if(s){s.value='" . $status . "';s.dispatchEvent(new Event('change',{bubbles:true}));s.dispatchEvent(new Event('input',{bubbles:true}));}" .
            "setTimeout(function(){document.querySelector('button[dusk=create-button],button[dusk=update-button]')?.click();},100);" .
        "})()";
    }
}
