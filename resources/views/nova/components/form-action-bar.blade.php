@php
    $secondaryActionStyles = [
        'success-link' => 'display:inline-flex;align-items:center;height:36px;padding:0 4px;font-size:14px;font-weight:500;background:none;border:none;cursor:pointer;white-space:nowrap;color:#16a34a;text-decoration:underline;',
        'danger-link' => 'display:inline-flex;align-items:center;height:36px;padding:0 4px;font-size:14px;font-weight:500;background:none;border:none;cursor:pointer;white-space:nowrap;color:#dc2626;text-decoration:underline;',
        'neutral-link' => 'display:inline-flex;align-items:center;height:36px;padding:0 4px;font-size:14px;font-weight:500;background:none;border:none;cursor:pointer;white-space:nowrap;color:#475569;text-decoration:underline;',
    ];
    $saveButtonStyle = 'display:inline-flex;align-items:center;height:36px;padding:0 12px;font-size:14px;font-weight:700;border-radius:4px;border:1px solid #1f2937;box-shadow:0 1px 2px rgba(0,0,0,.05);cursor:pointer;white-space:nowrap;line-height:1;background:#111827;color:#fff;';
    $copyIcon = "<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='9' y='9' width='13' height='13' rx='2' ry='2'/><path d='M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1'/></svg>";
@endphp

<div id="nova-info-bar" data-form-action-bar="1" style="display:flex;align-items:center;gap:8px;justify-content:flex-end;padding:0;">
    @if (!empty($heading))
        <span style="flex:1;font-size:14px;font-weight:600;color:#0f172a;">{{ $heading }}</span>
    @elseif (!empty($linkBlock['url']))
        <span style="flex:1;min-width:0;display:flex;flex-direction:column;gap:3px;">
            <span style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                <span style="font-size:10px;font-weight:700;letter-spacing:0.08em;color:#9ca3af;text-transform:uppercase;white-space:nowrap;">{{ $linkBlock['eyebrow'] }}</span>
                <span style="display:inline-flex;align-items:center;gap:6px;width:fit-content;max-width:100%;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:4px;padding:3px 4px 3px 6px;">
                    <a href="{{ $linkBlock['url'] }}" target="_blank" style="margin-top:1px;font-size:11px;color:#0f172a;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;">{{ $linkBlock['url'] }}</a>
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
                <span style="font-size:11px;color:#64748b;line-height:1.5;">{{ $linkBlock['notice'] }}</span>
            @endif
        </span>
    @elseif (!empty($metaBlock['items']))
        <span style="flex:1;min-width:0;display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
            @foreach ($metaBlock['items'] as $item)
                <span style="display:flex;flex-direction:column;gap:2px;min-width:0;">
                    <span style="font-size:10px;font-weight:700;letter-spacing:0.08em;color:#9ca3af;text-transform:uppercase;white-space:nowrap;">{{ $item['label'] }}</span>
                    <span style="font-size:11px;color:#0f172a;white-space:nowrap;">{{ $item['value'] }}</span>
                </span>
            @endforeach
        </span>
    @else
        <span style="flex:1;"></span>
    @endif

    @if ($secondaryAction)
        <button
            type="button"
            onclick="{{ $secondaryAction['js'] }}"
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
</div>
