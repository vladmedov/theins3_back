@php
    $rootStyle = $withOuterCard
        ? 'margin-top:16px;border:1px solid #e2e8f0;border-radius:10px;background:#ffffff;overflow:hidden;'
        : '';
    $listStyle = $withOuterCard ? 'padding:8px;' : 'padding:2px 0 0;';
    $footerStyle = $withOuterCard
        ? 'display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;border-top:1px solid #eef2f7;background:#f8fafc;'
        : 'display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0 0;border-top:1px solid #eef2f7;';
@endphp

<div style="{{ $rootStyle }}">
    @if ($showHeader)
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid #eef2f7;background:#f8fafc;">
            <div style="font-weight:600;color:#0f172a;">{{ __('related_posts_panel.heading') }}</div>
            <div style="font-size:12px;color:#64748b;">{{ __('related_posts_panel.total', ['count' => $total]) }}</div>
        </div>
    @endif
    <div style="{{ $listStyle }}">
        @foreach ($posts as $post)
            @php
                $metaParts = array_filter([
                    $post['type_label'],
                    '#' . $post['id'],
                    $post['published_at'],
                ]);
                $itemInnerStyle = $withOuterCard
                    ? 'padding:10px 12px;border:1px solid #eef2f7;border-radius:8px;background:#fff;'
                    : 'padding:10px 0;border-bottom:' . ($loop->last ? 'none' : '1px solid #eef2f7') . ';background:transparent;';
                $itemOuterStyle = $withOuterCard
                    ? 'display:block;margin-bottom:8px;text-decoration:none;'
                    : 'display:block;text-decoration:none;';
            @endphp

            @if ($post['edit_url'])
                <a href="{{ $post['edit_url'] }}" class="link-default" style="{{ $itemOuterStyle }}">
                    <div style="{{ $itemInnerStyle }}">
                        <div style="font-weight:500;color:#0f172a;">{{ $post['title'] }}</div>
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:4px;font-size:12px;color:#64748b;">
                            <span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-weight:600;">{{ $post['type_label'] }}</span>
                            @foreach ($metaParts as $metaPart)
                                @if ($loop->first)
                                    @continue
                                @endif
                                <span>{{ $metaPart }}</span>
                            @endforeach
                        </div>
                    </div>
                </a>
            @else
                <div style="{{ $withOuterCard ? 'margin-bottom:8px;' : '' }}">
                    <div style="{{ $itemInnerStyle }}">
                        <div style="font-weight:500;color:#0f172a;">{{ $post['title'] }}</div>
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:4px;font-size:12px;color:#64748b;">
                            <span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-weight:600;">{{ $post['type_label'] }}</span>
                            @foreach ($metaParts as $metaPart)
                                @if ($loop->first)
                                    @continue
                                @endif
                                <span>{{ $metaPart }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    @if ($lastPage > 1)
        <div style="{{ $footerStyle }}">
            <div style="color:#64748b;font-size:12px;">
                {{ $withOuterCard
                    ? __('related_posts_panel.page_of', ['current' => $currentPage, 'last' => $lastPage])
                    : __('related_posts_panel.summary', ['count' => $total, 'current' => $currentPage, 'last' => $lastPage]) }}
            </div>
            <div style="display:flex;align-items:center;gap:14px;font-size:13px;">
                @if ($previousUrl)
                    <a href="{{ $previousUrl }}" class="link-default" style="text-decoration:none;font-weight:500;">{{ __('related_posts_panel.previous') }}</a>
                @else
                    <span style="color:#94a3b8;">{{ __('related_posts_panel.previous') }}</span>
                @endif

                @if ($nextUrl)
                    <a href="{{ $nextUrl }}" class="link-default" style="text-decoration:none;font-weight:500;">{{ __('related_posts_panel.next') }}</a>
                @else
                    <span style="color:#94a3b8;">{{ __('related_posts_panel.next') }}</span>
                @endif
            </div>
        </div>
    @elseif (!$withOuterCard)
        <div style="{{ $footerStyle }}">
            <div style="color:#64748b;font-size:12px;">
                {{ __('related_posts_panel.summary', ['count' => $total, 'current' => $currentPage, 'last' => $lastPage]) }}
            </div>
        </div>
    @endif
</div>
