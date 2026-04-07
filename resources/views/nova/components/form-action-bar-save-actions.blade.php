@php
    $actionsVisibilityClass = ! empty($postEditLockEnabled)
        ? ''
        : (! empty($initialCanEdit) ? '' : ' hidden');
    $includeAutosave = $includeAutosave ?? true;
@endphp
<span class="nova-form-action-bar__actions{{ $actionsVisibilityClass }}">
    <span class="nova-form-action-bar__actions-row">
        @if ($stayAction)
            <button
                type="button"
                data-nova-form-action-bar-click="{{ !empty($stayAction['js']) && str_contains($stayAction['js'], 'saveWithoutReload') ? 'custom-save' : 'native' }}"
                data-saving-label="{{ $stayAction['savingLabel'] }}"
                data-autosave-save-default-label="{{ e($stayAction['label']) }}"
                @if (!is_null($stayAction['originalStatus']))
                    data-original-status="{{ $stayAction['originalStatus'] }}"
                @endif
                style="{{ ($stayAction['variant'] ?? null) === 'primary' ? $saveButtonStyle : ($secondaryActionStyles[$stayAction['variant']] ?? $secondaryActionStyles['neutral-link']) }}"
            >{{ $stayAction['label'] }}</button>
        @endif

        @if ($secondaryAction)
            <button
                type="button"
                data-toggle-publish-action="1"
                data-label-when-published="{{ __('Unpublish') }}"
                data-label-when-draft="{{ __('Publish') }}"
                data-variant-when-published="danger-link"
                data-variant-when-draft="success-link"
                onclick="window.NovaFormActionBar && window.NovaFormActionBar.togglePublish && window.NovaFormActionBar.togglePublish()"
                style="{{ $secondaryActionStyles[$secondaryAction['variant']] ?? $secondaryActionStyles['neutral-link'] }}"
            >{{ $secondaryAction['label'] }}</button>
        @endif

        @if ($saveAction)
            <button
                type="button"
                data-nova-form-action-bar-click="native"
                style="{{ $saveButtonStyle }}"
            >{{ $saveAction['label'] }}</button>
        @endif
    </span>

    @if ($includeAutosave && $hasSaveStatusRow)
        @include('nova.components.form-action-bar-autosave', ['autosave' => $autosave, 'autosaveVariant' => 'stacked'])
    @endif
</span>
