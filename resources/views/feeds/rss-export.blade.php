<rss version="2.0"
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:media="http://search.yahoo.com/mrss/">
  <channel>
    <title>THE INSIDER</title>
    <link>{{ $siteUrl }}</link>
    <description>{{ $description }}</description>
    <language>{{ $language }}</language>
    <image>
      <url>{{ $siteUrl }}/client_files/logo-small.png</url>
      <title>THE INSIDER</title>
      <link>{{ $siteUrl }}</link>
    </image>
    <lastBuildDate>{{ now()->toRssString() }}</lastBuildDate>
    <atom:link href="{{ $selfUrl }}" rel="self" type="application/rss+xml"/>
    <atom:link href="{{ $pageUrl(1) }}" rel="first" type="application/rss+xml"/>
@if($page > 1)
    <atom:link href="{{ $pageUrl($page - 1) }}" rel="prev" type="application/rss+xml"/>
@endif
@if($page < $totalPages)
    <atom:link href="{{ $pageUrl($page + 1) }}" rel="next" type="application/rss+xml"/>
@endif
    <atom:link href="{{ $pageUrl($totalPages) }}" rel="last" type="application/rss+xml"/>
@foreach($posts as $post)
@php
    $postUrl = App\Services\ContentRenderer::getPostUrl($post);
    $imageUrl = App\Services\ContentRenderer::getPostImageUrl($post);
    $authorName = App\Services\ContentRenderer::getAuthorName($post);
@endphp
    <item>
      <title><![CDATA[{{ $post->title }}]]></title>
      <link>{{ $postUrl }}</link>
      <guid>{{ $postUrl }}</guid>
      <dc:creator><![CDATA[{{ $authorName }}]]></dc:creator>
@if($imageUrl)
      <enclosure url="{{ $imageUrl }}" length="0" type="image/jpeg"/>
@endif
      <description><![CDATA[{!! $post->lead ?? '' !!}]]></description>
      <content:encoded><![CDATA[{!! App\Services\ContentRenderer::renderToHtml($post) !!}]]></content:encoded>
      <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
    </item>
@endforeach
  </channel>
</rss>
