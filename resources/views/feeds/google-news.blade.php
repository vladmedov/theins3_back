<rss version="2.0"
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:media="http://search.yahoo.com/mrss/">
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
@endphp
    <item>
      <title><![CDATA[{{ $post->title }}]]></title>
      <link>{{ $postUrl }}</link>
      <description><![CDATA[{{ $post->lead ?? '' }}]]></description>
      <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
      <guid isPermaLink="true">{{ $postUrl }}</guid>
      <source url="{{ $siteUrl }}">The Insider</source>
      <dc:creator><![CDATA[{{ $authorName }}]]></dc:creator>
      <category><![CDATA[{{ $post->category->title }}]]></category>
@if($imageUrl)
      <enclosure url="{{ $imageUrl }}" type="image/jpeg" length="0"/>
      <media:content url="{{ $imageUrl }}" medium="image">
        <media:credit role="author">The Insider</media:credit>
      </media:content>
@endif
    </item>
@endforeach
  </channel>
</rss>
