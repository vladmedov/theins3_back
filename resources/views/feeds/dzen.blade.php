<rss version="2.0"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:media="http://search.yahoo.com/mrss/"
     xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>The Insider</title>
    <link>{{ $siteUrl }}</link>
    <description>{{ $description }}</description>
    <language>{{ $language }}</language>
    <lastBuildDate>{{ now()->toRssString() }}</lastBuildDate>
    <atom:link href="{{ $selfUrl }}" rel="self" type="application/rss+xml"/>
@foreach($posts as $post)
@php
    $postUrl = App\Services\ContentRenderer::getPostUrl($post);
    $imageUrl = App\Services\ContentRenderer::getPostImageUrl($post);
    $authorName = App\Services\ContentRenderer::getAuthorName($post);
    $renderedContent = App\Services\ContentRenderer::renderToHtml($post);
@endphp
    <item>
      <title><![CDATA[{{ $post->title }}]]></title>
      <link>{{ $postUrl }}</link>
      <description><![CDATA[{{ $post->lead ?? '' }}]]></description>
      <content:encoded><![CDATA[{!! $renderedContent !!}]]></content:encoded>
      <dc:creator>{{ $authorName }}</dc:creator>
      <category><![CDATA[{{ $post->category->title }}]]></category>
      <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
      <guid isPermaLink="true">{{ $postUrl }}</guid>
@if($imageUrl)
      <media:content url="{{ $imageUrl }}" medium="image"/>
@endif
    </item>
@endforeach
  </channel>
</rss>
