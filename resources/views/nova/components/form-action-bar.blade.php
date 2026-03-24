@php
    $secondaryActionStyles = [
        'success-link' => 'display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:0 4px;font-size:14px;font-weight:500;background:none;border:none;cursor:pointer;white-space:nowrap;color:#16a34a;text-decoration:underline;',
        'danger-link' => 'display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:0 4px;font-size:14px;font-weight:500;background:none;border:none;cursor:pointer;white-space:nowrap;color:#dc2626;text-decoration:underline;',
        'neutral-link' => 'display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:0 4px;font-size:14px;font-weight:500;background:none;border:none;cursor:pointer;white-space:nowrap;color:#475569;text-decoration:underline;',
    ];
    $saveButtonStyle = 'display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:0 12px;font-size:14px;font-weight:700;border-radius:4px;border:1px solid #1f2937;box-shadow:0 1px 2px rgba(0,0,0,.05);cursor:pointer;white-space:nowrap;line-height:1;background:#111827;color:#fff;box-sizing:border-box;';
    $copyIcon = "<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='9' y='9' width='13' height='13' rx='2' ry='2'/><path d='M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1'/></svg>";
    $hasSaveStatusRow = isset($autosave) && is_array($autosave);
    $hasScrollNav = !empty($scrollNav['direction']) && in_array($scrollNav['direction'], ['up', 'down'], true);
@endphp

<style>
    .nova-form-action-bar {
        display: flex;
        align-items: center;
        gap: 8px;
        justify-content: flex-end;
        padding: 0;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
    }
    .nova-form-action-bar--with-scroll {
        position: relative;
        overflow: visible;
        padding-left: 20px;
        border-top-left-radius: 0 !important;
        border-bottom-left-radius: 0 !important;
    }
    .nova-form-action-bar__scroll {
        position: absolute;
        top: -16px;
        left: -32px;
        bottom: -16px;
        width: 28px;
        min-width: 28px;
        padding: 0;
        border: 1px solid #e2e8f0;
        border-right: none;
        border-radius: 6px 0 0 6px;
        background: rgba(100, 116, 139, 0.08);
        color: #64748b;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        opacity: .9;
    }
    .nova-form-action-bar__scroll:hover {
        background: rgba(100, 116, 139, 0.14);
        color: #334155;
    }
    .nova-form-action-bar__scroll svg {
        width: 16px;
        height: calc(100% - 2px);
        display: block;
    }
    .nova-form-action-bar__main {
        flex: 1;
        min-width: 0;
    }
    .nova-form-action-bar__actions {
        display: inline-flex;
        flex-direction: column;
        align-items: stretch;
        gap: 6px;
        min-width: 0;
        flex-shrink: 0;
    }
    .nova-form-action-bar__actions-row {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        justify-content: flex-end;
        flex-wrap: wrap;
    }
    .nova-form-action-bar__url-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        width: fit-content;
        max-width: 100%;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        padding: 3px 4px 3px 6px;
        box-sizing: border-box;
    }
    .nova-form-action-bar__url-link {
        margin-top: 1px;
        font-size: 12px;
        font-weight: 600;
        color: #0f172a;
        text-decoration: none;
        line-height: 1.2;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: min(100vw - 120px, 520px);
    }
    @media (max-width: 768px) {
        .nova-form-action-bar {
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
        }
        .nova-form-action-bar__main {
            flex: none;
            width: 100%;
        }
        .nova-form-action-bar__actions {
            width: 100%;
        }
        .nova-form-action-bar__actions-row {
            flex-direction: column;
            align-items: stretch;
            justify-content: stretch;
            gap: 10px;
        }
        .nova-form-action-bar__actions-row > button {
            width: 100% !important;
            min-height: 44px !important;
            justify-content: center !important;
            box-sizing: border-box !important;
        }
        .nova-form-action-bar__autosave {
            justify-content: flex-start !important;
            text-align: left !important;
        }
        .nova-form-action-bar__autosave [data-autosave-status-text="1"] {
            white-space: normal !important;
            text-align: left;
        }
        .nova-form-action-bar__url-row {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 6px !important;
        }
        .nova-form-action-bar__url-chip {
            width: 100%;
            max-width: 100%;
        }
        .nova-form-action-bar__url-link {
            white-space: normal !important;
            word-break: break-all;
            max-width: 100%;
        }
        .nova-form-action-bar__meta {
            gap: 16px !important;
        }
    }
</style>

<div
    class="nova-form-action-bar{{ $hasScrollNav ? ' nova-form-action-bar--with-scroll' : '' }}"
    id="nova-info-bar"
    data-form-action-bar="1"
    @if (!empty($autosave['enabled']))
        data-autosave-enabled="1"
    @endif
>
    @if ($hasScrollNav)
        <button
            type="button"
            class="nova-form-action-bar__scroll"
            data-scroll-nav="{{ $scrollNav['direction'] }}"
            title="{{ $scrollNav['title'] }}"
            aria-label="{{ $scrollNav['title'] }}"
            onclick="window.scrollTo({ top: this.dataset.scrollNav === 'up' ? 0 : (document.documentElement.scrollHeight || document.body.scrollHeight || 0), behavior: 'smooth' })"
        >
            @if ($scrollNav['direction'] === 'up')
                <svg aria-hidden="true" viewBox="0 0 16 100" preserveAspectRatio="none" fill="none">
                    <line x1="8" x2="8" y1="90" y2="10" stroke="currentColor" stroke-width="2" stroke-linecap="round"></line>
                    <polyline points="4,18 8,10 12,18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"></polyline>
                </svg>
            @else
                <svg aria-hidden="true" viewBox="0 0 16 100" preserveAspectRatio="none" fill="none">
                    <line x1="8" x2="8" y1="10" y2="90" stroke="currentColor" stroke-width="2" stroke-linecap="round"></line>
                    <polyline points="4,82 8,90 12,82" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"></polyline>
                </svg>
            @endif
        </button>
    @endif

    @if (!empty($heading))
        <span class="nova-form-action-bar__main" style="font-size:14px;font-weight:600;color:#0f172a;">{{ $heading }}</span>
    @elseif (!empty($linkBlock['url']))
        <span class="nova-form-action-bar__main" style="display:flex;flex-direction:column;gap:3px;">
            <span class="nova-form-action-bar__url-row" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                <span style="font-size:10px;font-weight:700;letter-spacing:0.08em;color:#9ca3af;text-transform:uppercase;white-space:nowrap;">{{ $linkBlock['eyebrow'] }}</span>
                <span class="nova-form-action-bar__url-chip">
                    <a href="{{ $linkBlock['url'] }}" target="_blank" class="nova-form-action-bar__url-link">{{ $linkBlock['url'] }}</a>
                    @if (!empty($linkBlock['copyable']))
                        <button
                            type="button"
                            class="js-copy-post-url"
                            data-copy-url="{{ $linkBlock['url'] }}"
                            title="{{ $linkBlock['copyTitle'] }}"
                            style="flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;padding:0;border:none;border-radius:3px;background:#fff;color:#64748b;cursor:pointer;box-shadow:0 1px 1px rgba(0,0,0,.06);"
                            onmouseover='this.style.background="#e2e8f0";this.style.color="#334155"'
                            onmouseout='this.style.background="#fff";this.style.color="#64748b"'
                        >{!! $copyIcon !!}</button>
                    @endif
                </span>
            </span>

            @if (!empty($linkBlock['notice']))
                <span
                    style="font-size:11px;color:#64748b;line-height:1.5;"
                    @if (!empty($linkBlock['noticeExpiresAt']))
                        data-preview-notice="1"
                        data-preview-expires-at="{{ $linkBlock['noticeExpiresAt'] }}"
                        data-preview-prefix="{{ $linkBlock['noticePrefix'] ?? '' }}"
                        data-preview-suffix="{{ $linkBlock['noticeSuffix'] ?? '' }}"
                    @endif
                >{{ $linkBlock['notice'] }}</span>
            @endif
        </span>
    @elseif (!empty($metaBlock['items']))
        <span class="nova-form-action-bar__main nova-form-action-bar__meta" style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
            @foreach ($metaBlock['items'] as $item)
                <span style="display:flex;flex-direction:column;gap:2px;min-width:0;">
                    <span style="font-size:10px;font-weight:700;letter-spacing:0.08em;color:#9ca3af;text-transform:uppercase;white-space:nowrap;">{{ $item['label'] }}</span>
                    <span style="font-size:11px;color:#0f172a;white-space:nowrap;">{{ $item['value'] }}</span>
                </span>
            @endforeach
        </span>
    @else
        <span class="nova-form-action-bar__main" style="flex:1;"></span>
    @endif

    @if ($secondaryAction || $stayAction || $saveAction || $hasSaveStatusRow)
        <span class="nova-form-action-bar__actions">
            <span class="nova-form-action-bar__actions-row">
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

                @if ($stayAction)
                    <button
                        type="button"
                        onclick="{{ $stayAction['js'] }}"
                        data-saving-label="{{ $stayAction['savingLabel'] }}"
                        @if (!is_null($stayAction['originalStatus']))
                            data-original-status="{{ $stayAction['originalStatus'] }}"
                        @endif
                        style="{{ ($stayAction['variant'] ?? null) === 'primary' ? $saveButtonStyle : ($secondaryActionStyles[$stayAction['variant']] ?? $secondaryActionStyles['neutral-link']) }}"
                    >{{ $stayAction['label'] }}</button>
                @endif

                @if ($saveAction)
                    <button
                        type="button"
                        onclick="{{ $saveAction['js'] }}"
                        style="{{ $saveButtonStyle }}"
                    >{{ $saveAction['label'] }}</button>
                @endif
            </span>

            @if ($hasSaveStatusRow)
                <span
                    class="nova-form-action-bar__autosave"
                    style="display:inline-flex;align-items:center;justify-content:flex-end;width:100%;min-height:20px;padding-top:6px;border-top:1px solid #e2e8f0;font-size:11px;color:#64748b;line-height:1.2;text-align:right;box-sizing:border-box;"
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
                    <span
                        style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;line-height:20px;"
                        data-autosave-status-text="1"
                    >{{ $autosave['idleLabel'] }}</span>
                    <span
                        style="display:none;align-items:center;height:20px;margin-left:8px;padding:0 6px;border-radius:9999px;background:#f1f5f9;color:#475569;white-space:nowrap;box-sizing:border-box;flex-shrink:0;"
                        data-autosave-countdown-text="1"
                    ></span>
                </span>
            @endif
        </span>
    @endif
</div>
