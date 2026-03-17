<?php

namespace Medov\InsertionCode;

use Laravel\Nova\Fields\Field;

class InsertionCode extends Field
{
    public $component = 'insertion-code';

    protected bool $defaultForFrontend = true;
    protected string $layoutType = '';

    public function __construct($name = null, $attribute = 'show_insertion_code', $resolveCallback = null)
    {
        parent::__construct($name ?? __('insertion_code.label'), $attribute, $resolveCallback);
    }

    public function forLayout(string $layoutType): static
    {
        $this->layoutType = $layoutType;
        return $this;
    }

    public function default($value): static
    {
        if (!is_callable($value)) {
            $this->defaultForFrontend = (bool) $value;
        }
        return parent::default($value);
    }

    public function resolve($resource, ?string $attribute = null): void
    {
        parent::resolve($resource, $attribute);

        $insertionCode = data_get($resource, '_insertion_code', '');
        $this->meta['insertionCode'] = $insertionCode;
    }

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'defaultEnabled' => $this->defaultForFrontend,
            'layoutType'     => $this->layoutType,
            'hintOff'        => __('insertion_code.hint_off'),
            'copyTitle'      => __('insertion_code.copy_title'),
            'copiedTitle'    => __('insertion_code.copied_title'),
        ]);
    }
}
