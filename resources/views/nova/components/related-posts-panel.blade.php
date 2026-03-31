@php
    $rootStyle = $withOuterCard
        ? 'margin-top:16px;border:1px solid #e2e8f0;border-radius:10px;background:#ffffff;overflow:hidden;'
        : '';
    $listStyle = $withOuterCard ? 'padding:0;' : 'padding:2px 0 0;';
    $footerStyle = $withOuterCard
        ? 'display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;border-top:1px solid #eef2f7;background:#f8fafc;'
        : 'display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0 0;border-top:1px solid #eef2f7;';
@endphp

<div class="font-sans" style="{{ $rootStyle }}">
    @if ($showHeader)
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid #eef2f7;background:#f8fafc;">
            <div class="uppercase tracking-wide text-s text-90" style="font-weight:400;">{{ __('related_posts_panel.heading') }}</div>
            <div class="text-xs text-80">{{ __('related_posts_panel.total', ['count' => $total]) }}</div>
        </div>
    @endif
    <div style="{{ $listStyle }}">
        @foreach ($posts as $post)
            @php
                $metaParts = array_filter([
                    '#' . $post['id'],
                    $post['published_at'],
                    $post['authors'] ?? null,
                ]);
                $metaLine = implode(' • ', $metaParts);
                $itemInnerStyle = $withOuterCard
                    ? 'padding:10px 16px;border-bottom:' . ($loop->last ? 'none' : '1px solid #eef2f7') . ';background:#fff;'
                    : 'padding:10px 0;border-bottom:' . ($loop->last ? 'none' : '1px solid #eef2f7') . ';background:transparent;';
                $itemOuterStyle = 'display:block;text-decoration:none;';
            @endphp

            @if ($post['edit_url'])
                <a href="{{ $post['edit_url'] }}" class="link-default" style="{{ $itemOuterStyle }}">
                    <div style="{{ $itemInnerStyle }}">
                        <div class="nova_view_post_title text-90" style="font-family:'RF Dewi', ui-sans-serif, system-ui, sans-serif;font-weight:300;">
                            <span style="color:#dc2626;">{{ $post['type_label'] }}</span>: {{ $post['title'] }}
                        </div>
                        @if ($metaLine !== '')
                            <div class="text-xs" style="margin-top:4px;color:#94a3b8;">{{ $metaLine }}</div>
                        @endif
                    </div>
                </a>
            @else
                <div style="{{ $withOuterCard ? 'margin-bottom:8px;' : '' }}">
                    <div style="{{ $itemInnerStyle }}">
                        <div class="nova_view_post_title text-90" style="font-family:'RF Dewi', ui-sans-serif, system-ui, sans-serif;font-weight:300;">
                            <span style="color:#dc2626;">{{ $post['type_label'] }}</span>: {{ $post['title'] }}
                        </div>
                        @if ($metaLine !== '')
                            <div class="text-xs" style="margin-top:4px;color:#94a3b8;">{{ $metaLine }}</div>
                        @endif
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    @if ($lastPage > 1)
        <div style="{{ $footerStyle }}">
            <div class="text-xs text-80">
                {{ $withOuterCard
                    ? __('related_posts_panel.page_of', ['current' => $currentPage, 'last' => $lastPage])
                    : __('related_posts_panel.summary', ['count' => $total, 'current' => $currentPage, 'last' => $lastPage]) }}
            </div>
            <div class="text-sm text-80" style="display:flex;align-items:center;gap:14px;">
                @if ($previousUrl)
                    <a href="{{ $previousUrl }}" class="link-default text-sm font-medium" style="text-decoration:none;">{{ __('related_posts_panel.previous') }}</a>
                @else
                    <span class="text-sm text-60">{{ __('related_posts_panel.previous') }}</span>
                @endif

                @if ($nextUrl)
                    <a href="{{ $nextUrl }}" class="link-default text-sm font-medium" style="text-decoration:none;">{{ __('related_posts_panel.next') }}</a>
                @else
                    <span class="text-sm text-60">{{ __('related_posts_panel.next') }}</span>
                @endif
            </div>
        </div>
    @elseif (!$withOuterCard)
        <div style="{{ $footerStyle }}">
            <div class="text-xs text-80">
                {{ __('related_posts_panel.summary', ['count' => $total, 'current' => $currentPage, 'last' => $lastPage]) }}
            </div>
        </div>
    @endif
</div>
