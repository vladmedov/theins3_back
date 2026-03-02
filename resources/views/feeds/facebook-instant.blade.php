<rss version="2.0"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>THE INSIDER</title>
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
      <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
      <guid isPermaLink="true">{{ $postUrl }}</guid>
      <description><![CDATA[{{ $post->lead ?? '' }}]]></description>
      <content:encoded><![CDATA[
<!DOCTYPE html>
<html lang="{{ $language }}" prefix="op: http://media.facebook.com/op#">
<head>
  <meta charset="utf-8">
  <link rel="canonical" href="{{ $postUrl }}">
  <meta property="op:markup_version" content="v1.0">
</head>
<body>
  <article>
    <header>
      <h1>{{ $post->title }}</h1>
      <time class="op-published" datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('d.m.Y H:i') }}</time>
@if($post->updated_at && $post->updated_at->gt($post->published_at))
      <time class="op-modified" datetime="{{ $post->updated_at->toIso8601String() }}">{{ $post->updated_at->format('d.m.Y H:i') }}</time>
@endif
      <address><a>{{ $authorName }}</a></address>
@if($imageUrl)
      <figure><img src="{{ $imageUrl }}"/></figure>
@endif
@if($post->lead)
      <h2>{{ $post->lead }}</h2>
@endif
    </header>
    {!! $renderedContent !!}
  </article>
</body>
</html>
      ]]></content:encoded>
    </item>
@endforeach
  </channel>
</rss>
