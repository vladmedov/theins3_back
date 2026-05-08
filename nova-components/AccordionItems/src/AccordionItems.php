<?php

namespace Medov\AccordionItems;

use Laravel\Nova\Fields\Field;

class AccordionItems extends Field
{
    /**
     * The field's component.
     */
    public $component = 'accordion-items';

    /**
     * Name of the CKEditor toolbar configuration to use for each item's body.
     * See `config/nova-ckeditor.php` (`toolbar-theins-medium` etc.).
     */
    protected string $toolbarName = 'toolbar-theins-medium';

    public function toolbar(string $name): static
    {
        $this->toolbarName = $name;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'toolbarName' => $this->toolbarName,
            'toolbarConfig' => $this->resolveToolbarConfig($this->toolbarName),
            'uiLanguage' => app()->getLocale() === 'ru' ? 'ru' : 'en',
        ]);
    }

    /**
     * Build a config payload for CKEditor on the JS side, mirroring the shape used by
     * `mostafaznv/nova-ckeditor`'s field. Only fields that the AccordionItems Vue
     * component actually consumes are populated.
     *
     * @return array<string, mixed>
     */
    protected function resolveToolbarConfig(string $toolbarName): array
    {
        $toolbar = config('nova-ckeditor.toolbars.' . $toolbarName, []);

        return [
            'items' => $toolbar['items'] ?? [],
            'options' => $toolbar['options'] ?? [],
            'height' => $toolbar['height'] ?? 200,
            'contentLanguage' => $toolbar['content-lang'] ?? 'en',
            'forcePasteAsPlainText' => (bool) ($toolbar['force-paste-as-plain-text'] ?? false),
            'stripInlineStylesOnPaste' => (bool) ($toolbar['strip-inline-styles-on-paste'] ?? true),
            'shouldNotGroupWhenFull' => (bool) ($toolbar['should-not-group-when-full'] ?? false),
            'htmlSupport' => $toolbar['html-support'] ?? ['allow' => [], 'disallow' => []],
            'textPartLanguage' => $toolbar['text-part-language'] ?? [],
        ];
    }
}
