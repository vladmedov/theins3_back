@php
    $variant = $autosaveVariant ?? 'stacked';
    $isInline = $variant === 'inline';
@endphp
<span
    class="nova-form-action-bar__autosave{{ $isInline ? ' nova-form-action-bar__autosave--inline' : ' nova-form-action-bar__autosave--stacked' }}"
    data-autosave-status-root="1"
    data-autosave-label="{{ $autosave['statusLabel'] }}"
    data-autosave-idle-label="{{ $autosave['idleLabel'] }}"
    data-last-saved-label="{{ $autosave['lastSavedLabel'] }}"
    data-last-saved-date-prefix="{{ $autosave['lastSavedDatePrefix'] ?? '' }}"
    data-autosave-failure-label="{{ __('form_action_bar.autosave_failed') }}"
    data-autosave-countdown-prefix="{{ __('form_action_bar.autosave_in') }}"
    data-autosave-countdown-suffix="{{ __('form_action_bar.autosave_seconds_short') }}"
    @if (!empty($autosave['updatedAtIso']))
        data-last-saved-at="{{ $autosave['updatedAtIso'] }}"
    @endif
>
    <span class="nova-form-action-bar__autosave-line">
        <span
            class="nova-post-edit-lock__label nova-form-action-bar__autosave-caption"
            data-autosave-caption="1"
            style="display:none"
        ></span>
        <span
            class="nova-form-action-bar__autosave-status-text"
            data-autosave-status-text="1"
        >{{ $autosave['idleLabel'] }}</span>
    </span>
    <span
        class="nova-form-action-bar__autosave-countdown"
        data-autosave-countdown-text="1"
    ></span>
</span>
