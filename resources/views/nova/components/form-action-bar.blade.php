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
    $postEditLockEnabled = !empty($postEditLockEnabled);
    $postEditLockActive = $postEditLockEnabled && !empty($postEditLockMeta) && is_array($postEditLockMeta);
@endphp

<div
    class="nova-form-action-bar{{ $hasScrollNav ? ' nova-form-action-bar--with-scroll' : '' }}{{ !empty($postEditLockEnabled) ? ' nova-form-action-bar--post-edit-lock' : '' }}"
    id="nova-info-bar"
    data-form-action-bar="1"
    @if ($postEditLockEnabled)
        data-post-edit-lock-enabled="1"
        data-nova-post-edit-disabled="{{ !empty($initialCanEdit) ? '0' : '1' }}"
        @if (!empty($initialCanEdit))
            data-post-edit-lock-initial-can-edit="1"
        @endif
    @endif
    @if (!empty($autosave['enabled']))
        data-autosave-enabled="1"
    @endif
    @if ($postEditLockActive)
        @foreach ($postEditLockMeta as $metaKey => $metaValue)
            @if ($metaValue !== null && $metaValue !== '')
                data-{{ $metaKey }}="{{ e($metaValue) }}"
            @endif
        @endforeach
    @endif
>
    @if ($hasScrollNav)
        <button
            type="button"
            class="nova-form-action-bar__scroll nova-form-action-bar__segment--scroll"
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

    @if (!empty($postEditLockEnabled))
        <span
            class="nova-form-action-bar__main nova-form-action-bar__main--post-edit-lock nova-form-action-bar__segment--main"
        >
            @if (!empty($linkBlock['url']))
                <span class="nova-form-action-bar__url-col nova-form-action-bar__url-col--with-lock" style="display:flex;flex-direction:column;gap:3px;">
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
            @endif
        </span>
    @elseif (!empty($linkBlock['url']))
        <span class="nova-form-action-bar__main nova-form-action-bar__segment--main" style="display:flex;flex-direction:column;gap:3px;">
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
        <span class="nova-form-action-bar__main nova-form-action-bar__meta nova-form-action-bar__segment--main" style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
            @foreach ($metaBlock['items'] as $item)
                <span style="display:flex;flex-direction:column;gap:2px;min-width:0;">
                    <span style="font-size:10px;font-weight:700;letter-spacing:0.08em;color:#9ca3af;text-transform:uppercase;white-space:nowrap;">{{ $item['label'] }}</span>
                    <span style="font-size:11px;color:#0f172a;white-space:nowrap;">{{ $item['value'] }}</span>
                </span>
            @endforeach
        </span>
    @else
        <span class="nova-form-action-bar__main nova-form-action-bar__segment--main" style="flex:1;"></span>
    @endif

    @if ($secondaryAction || $stayAction || $saveAction || $hasSaveStatusRow)
        @if (!empty($postEditLockEnabled))
            <span class="nova-form-action-bar__rail">
                <span
                    class="nova-form-action-bar__rail-panel nova-form-action-bar__rail-panel--editor"
                    data-post-edit-lock-panel="editor"
                    @if (empty($initialCanEdit)) hidden @endif
                >
                    @if (!empty($postEditLockLockHtml) || $hasSaveStatusRow)
                        <div class="nova-form-action-bar__rail-status-row nova-form-action-bar__rail-status-row--editor">
                            @if (!empty($postEditLockLockHtml))
                                <span class="nova-form-action-bar__rail-lock" data-post-edit-lock-center="1">{!! $postEditLockLockHtml !!}</span>
                            @endif
                            @if ($hasSaveStatusRow && !empty($autosave['enabled']))
                                <span class="nova-form-action-bar__autosave-hidden-for-js" aria-hidden="true">
                                    @include('nova.components.form-action-bar-autosave', ['autosave' => $autosave, 'autosaveVariant' => 'inline'])
                                </span>
                            @endif
                        </div>
                    @endif
                    @include('nova.components.form-action-bar-save-actions', ['includeAutosave' => false])
                </span>
                <span
                    class="nova-form-action-bar__rail-panel nova-form-action-bar__rail-panel--readonly"
                    data-post-edit-lock-panel="readonly"
                    @if (! empty($initialCanEdit)) hidden @endif
                >
                    @if (!empty($postEditLockLockHtml))
                        <div class="nova-form-action-bar__rail-status-row nova-form-action-bar__rail-status-row--readonly">
                            <span class="nova-form-action-bar__rail-lock" data-post-edit-lock-center="1">{!! $postEditLockLockHtml !!}</span>
                        </div>
                    @endif
                    <span class="nova-form-action-bar__takeover-rail">
                        <span class="nova-form-action-bar__actions-row">
                            <button type="button" class="nova-post-edit-lock__takeover" style="{{ $saveButtonStyle }}">{{ __('post_edit_lock.takeover') }}</button>
                        </span>
                    </span>
                </span>
                <span
                    class="nova-form-action-bar__rail-panel nova-form-action-bar__rail-panel--freed"
                    data-post-edit-lock-panel="freed"
                    hidden
                >
                    <div class="nova-form-action-bar__rail-status-row nova-form-action-bar__rail-status-row--freed">
                        <span class="nova-form-action-bar__rail-lock" data-post-edit-lock-center="1"></span>
                    </div>
                    <span class="nova-form-action-bar__takeover-rail">
                        <span class="nova-form-action-bar__actions-row">
                            <button type="button" class="nova-post-edit-lock__reload-to-edit" style="{{ $saveButtonStyle }}">{{ __('post_edit_lock.reload_to_edit_button') }}</button>
                        </span>
                    </span>
                </span>
            </span>
        @else
            @include('nova.components.form-action-bar-save-actions')
        @endif
    @endif
</div>
