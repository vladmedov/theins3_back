<?php

namespace App\Support\Nova;

class FormActionBar
{
    public static function render(array $options = []): string
    {
        $saveAction = array_merge([
            'label' => __('Save'),
            'js' => "document.querySelector('button[dusk=create-button],button[dusk=update-button]')?.click()",
        ], $options['saveAction'] ?? []);

        $secondaryAction = $options['secondaryAction'] ?? null;
        if ($secondaryAction) {
            $secondaryAction = array_merge([
                'label' => '',
                'js' => '',
                'variant' => 'neutral-link',
            ], $secondaryAction);
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

        return view('nova.components.form-action-bar', [
            'secondaryAction' => $secondaryAction,
            'saveAction' => $saveAction,
            'linkBlock' => $linkBlock,
            'metaBlock' => $metaBlock,
        ])->render();
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
}
