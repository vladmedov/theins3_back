<rss version="2.0"
     xmlns:yandex="http://news.yandex.ru"
     xmlns:media="http://search.yahoo.com/mrss/"
     xmlns:turbo="http://turbo.yandex.ru">
  <channel>
    <title>The Insider</title>
    <link>{{ $siteUrl }}</link>
    <description>{{ $description }}</description>
    <language>{{ $language }}</language>
    <lastBuildDate>{{ now()->toRssString() }}</lastBuildDate>
@foreach($posts as $post)
@php
    $postUrl = App\Services\ContentRenderer::getPostUrl($post);
    $imageUrl = App\Services\ContentRenderer::getPostImageUrl($post);
    $authorName = App\Services\ContentRenderer::getAuthorName($post);
    $renderedContent = App\Services\ContentRenderer::renderToHtml($post);
    $genre = match($post->type) {
        App\Enums\PostTypes::ARTICLE => 'article',
        App\Enums\PostTypes::NEWS => 'message',
        App\Enums\PostTypes::OPINION => 'opinion',
        default => 'article',
    };
@endphp
    <item>
      <title><![CDATA[{{ $post->title }}]]></title>
      <link>{{ $postUrl }}</link>
      <pdalink>{{ $postUrl }}</pdalink>
      <description><![CDATA[{{ $post->lead ?? '' }}]]></description>
      <yandex:full-text><![CDATA[{!! $renderedContent !!}]]></yandex:full-text>
      <yandex:genre>{{ $genre }}</yandex:genre>
      <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
      <author>{{ $authorName }}</author>
      <category><![CDATA[{{ $post->category->title }}]]></category>
@if($imageUrl)
      <enclosure url="{{ $imageUrl }}" type="image/jpeg" length="0"/>
@endif
    </item>
@endforeach
  </channel>
</rss>
