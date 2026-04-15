<?php

namespace App\Console\Commands;

use App\Enums\PostTypes;
use App\Models\Author;
use App\Models\Category;
use App\Models\InvestigationTheme;
use App\Models\Post;
use App\Models\Tag;
use App\Services\ContentRenderer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GenerateSitemap extends Command
{
    protected $signature = 'generate:sitemap';

    protected $description = 'Generate static sitemap XML files in the public directory';

    private const POSTS_PER_FILE = 1000;

    private array $generatedFiles = [];

    public function handle(): int
    {
        $startTime = microtime(true);

        $this->info('Generating sitemaps...');

        foreach (['ru', 'en'] as $lang) {
            $this->generateCategorySitemap($lang);
            $this->generateInvestigationSitemap($lang);
            $this->generateTagSitemap($lang);
            $this->generatePostSitemaps($lang);
            $this->generateNewsSitemaps($lang);
            $this->generateAuthorSitemap($lang);
            $this->generateColumnistSitemap($lang);
        }

        $this->generateSitemapIndex();
        $this->cleanupOldSitemaps();

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->info('Done! Generated '.count($this->generatedFiles)." sitemap files in {$elapsed}s");

        return Command::SUCCESS;
    }

    private function generatePostSitemaps(string $lang): void
    {
        $query = Post::where('status', Post::STATUS_PUBLISHED)
            ->where('language_code', $lang)
            ->whereNot('type', PostTypes::NEWS);

        $this->generateChunkedPostSitemaps($query, $lang, 'posts');
    }

    private function generateNewsSitemaps(string $lang): void
    {
        $query = Post::where('status', Post::STATUS_PUBLISHED)
            ->where('language_code', $lang)
            ->where('type', PostTypes::NEWS);

        $this->generateChunkedPostSitemaps($query, $lang, 'news');
    }

    /**
     * @param  Builder<Post>  $query
     */
    private function generateChunkedPostSitemaps(Builder $query, string $lang, string $basename): void
    {
        $chunkIndex = 0;

        $query->with(['category', 'columnist'])
            ->orderBy('published_at', 'desc')
            ->chunk(self::POSTS_PER_FILE, function ($posts) use ($lang, &$chunkIndex, $basename) {
                $chunkIndex++;
                $filename = "sitemap-{$basename}-{$lang}-{$chunkIndex}.xml";

                $publicationName = (string) config('services.google_news.publication_name');

                $entries = $posts->map(function (Post $post) use ($lang, $publicationName) {
                    $path = $this->getPostPath($post);
                    $lastmod = $post->updated_at->toW3cString();
                    $imageUrl = ContentRenderer::getPostImageUrl($post);
                    $isNews = $post->type === PostTypes::NEWS;
                    $publishedAt = $post->published_at ?? $post->updated_at;

                    return [
                        'loc' => $this->baseUrlForLanguage($lang).$path,
                        'lastmod' => $lastmod,
                        'image_url' => $imageUrl,
                        'is_news' => $isNews,
                        'news_publication_name' => $publicationName,
                        'news_language' => $post->language_code,
                        'news_publication_date' => $publishedAt->toW3cString(),
                        'news_title' => $post->title ?? '',
                    ];
                });

                $entriesArr = $entries->toArray();
                $this->writePostSitemap($filename, $entriesArr, $basename === 'news');
                $urlsForMax = array_map(static fn (array $e): array => [
                    'loc' => $e['loc'],
                    'lastmod' => $e['lastmod'],
                ], $entriesArr);
                $this->generatedFiles[] = [
                    'filename' => $filename,
                    'lastmod' => $this->maxLastmodForUrls($urlsForMax),
                    'lang' => $lang,
                ];

                $this->line("  {$filename}: {$entries->count()} URLs");
            });
    }

    private function generateCategorySitemap(string $lang): void
    {
        $homePath = $lang === 'ru' ? '/' : "/{$lang}";
        $baseUrl = $this->baseUrlForLanguage($lang);
        $newestPostAt = Post::where('status', Post::STATUS_PUBLISHED)
            ->where('language_code', $lang)
            ->max('updated_at');
        $homeLastmod = $newestPostAt
            ? Carbon::parse($newestPostAt)->toW3cString()
            : now()->toW3cString();

        $urls = collect([[
            'loc' => $baseUrl.$homePath,
            'lastmod' => $homeLastmod,
        ]]);

        $categories = Category::where('language_code', $lang)
            ->where('is_show_in_menu', true)
            ->get();
        $latestPostAtByCategory = $this->latestPublishedPostMaxUpdatedAtByCategory($lang);

        $categoryUrls = $categories->map(function (Category $category) use ($lang, $baseUrl, $latestPostAtByCategory) {
            $path = '/'.($lang === 'ru' ? '' : "{$lang}/").$category->slug;
            $row = $latestPostAtByCategory->get($category->id);

            return [
                'loc' => $baseUrl.$path,
                'lastmod' => $this->w3cLastmodFromMaxPostUpdated(
                    $row?->max_updated ?? null,
                    $category->updated_at
                ),
            ];
        });

        $urls = $urls->merge($categoryUrls);

        $filename = "sitemap-pages-{$lang}.xml";
        $urlsArr = $urls->toArray();
        $this->writeSitemap($filename, $urlsArr);
        $this->generatedFiles[] = [
            'filename' => $filename,
            'lastmod' => $this->maxLastmodForUrls($urlsArr),
            'lang' => $lang,
        ];
        $this->line("  {$filename}: {$urls->count()} URLs");
    }

    private function generateTagSitemap(string $lang): void
    {
        $tags = Tag::where('language_code', $lang)->get();
        if ($tags->isEmpty()) {
            return;
        }

        $filename = "sitemap-tags-{$lang}.xml";
        $baseUrl = $this->baseUrlForLanguage($lang);
        $latestPostAtByTag = $this->latestPublishedPostMaxUpdatedAtByTag($lang);

        $urls = $tags->map(function (Tag $tag) use ($lang, $baseUrl, $latestPostAtByTag) {
            $path = '/'.($lang === 'ru' ? '' : "{$lang}/")."tag/{$tag->slug}";
            $row = $latestPostAtByTag->get($tag->id);

            return [
                'loc' => $baseUrl.$path,
                'lastmod' => $this->w3cLastmodFromMaxPostUpdated(
                    $row?->max_updated ?? null,
                    $tag->updated_at
                ),
            ];
        });

        $urlsArr = $urls->toArray();
        $this->writeSitemap($filename, $urlsArr);
        $this->generatedFiles[] = [
            'filename' => $filename,
            'lastmod' => $this->maxLastmodForUrls($urlsArr),
            'lang' => $lang,
        ];
        $this->line("  {$filename}: {$urls->count()} URLs");
    }

    private function generateAuthorSitemap(string $lang): void
    {
        $authors = Author::where('language_code', $lang)
            ->where('is_author_page_hidden', false)
            ->whereExists(function ($query) use ($lang) {
                $query->select(\DB::raw(1))
                    ->from('posts')
                    ->join('post_authors', 'posts.id', '=', 'post_authors.post_id')
                    ->whereColumn('post_authors.author_id', 'authors.id')
                    ->where('posts.status', Post::STATUS_PUBLISHED)
                    ->where('posts.language_code', $lang)
                    ->whereIn('posts.type', [PostTypes::ARTICLE, PostTypes::NEWS, PostTypes::ONLINE]);
            })
            ->get();

        if ($authors->isEmpty()) {
            return;
        }

        $filename = "sitemap-authors-{$lang}.xml";
        $baseUrl = $this->baseUrlForLanguage($lang);
        $latestPostAtByAuthor = $this->latestPublishedPostMaxUpdatedAtByAuthor($lang);

        $urls = $authors->map(function (Author $author) use ($lang, $baseUrl, $latestPostAtByAuthor) {
            $path = '/'.($lang === 'ru' ? '' : "{$lang}/")."authors/{$author->slug}";
            $row = $latestPostAtByAuthor->get($author->id);

            return [
                'loc' => $baseUrl.$path,
                'lastmod' => $this->w3cLastmodFromMaxPostUpdated(
                    $row?->max_updated ?? null,
                    $author->updated_at
                ),
            ];
        });

        $urlsArr = $urls->toArray();
        $this->writeSitemap($filename, $urlsArr);
        $this->generatedFiles[] = [
            'filename' => $filename,
            'lastmod' => $this->maxLastmodForUrls($urlsArr),
            'lang' => $lang,
        ];
        $this->line("  {$filename}: {$urls->count()} URLs");
    }

    private function generateColumnistSitemap(string $lang): void
    {
        $columnists = Author::where('language_code', $lang)
            ->where('is_columnist_page_hidden', false)
            ->whereExists(function ($query) use ($lang) {
                $query->select(\DB::raw(1))
                    ->from('posts')
                    ->whereColumn('posts.columnist_id', 'authors.id')
                    ->where('posts.status', Post::STATUS_PUBLISHED)
                    ->where('posts.type', PostTypes::OPINION)
                    ->where('posts.language_code', $lang);
            })
            ->get();

        if ($columnists->isEmpty()) {
            return;
        }

        $filename = "sitemap-columnists-{$lang}.xml";
        $baseUrl = $this->baseUrlForLanguage($lang);
        $latestPostAtByColumnist = $this->latestPublishedPostMaxUpdatedAtByColumnist($lang);

        $urls = $columnists->map(function (Author $author) use ($lang, $baseUrl, $latestPostAtByColumnist) {
            $path = '/'.($lang === 'ru' ? '' : "{$lang}/")."opinions/{$author->slug}";
            $row = $latestPostAtByColumnist->get($author->id);

            return [
                'loc' => $baseUrl.$path,
                'lastmod' => $this->w3cLastmodFromMaxPostUpdated(
                    $row?->max_updated ?? null,
                    $author->updated_at
                ),
            ];
        });

        $urlsArr = $urls->toArray();
        $this->writeSitemap($filename, $urlsArr);
        $this->generatedFiles[] = [
            'filename' => $filename,
            'lastmod' => $this->maxLastmodForUrls($urlsArr),
            'lang' => $lang,
        ];
        $this->line("  {$filename}: {$urls->count()} URLs");
    }

    private function generateInvestigationSitemap(string $lang): void
    {
        $themes = InvestigationTheme::where('language_code', $lang)->get();
        if ($themes->isEmpty()) {
            return;
        }

        $filename = "sitemap-investigations-{$lang}.xml";
        $baseUrl = $this->baseUrlForLanguage($lang);
        $urls = $themes->map(function (InvestigationTheme $theme) use ($baseUrl) {
            return [
                'loc' => $baseUrl.$theme->getPath(),
                'lastmod' => $theme->updated_at?->toW3cString() ?? now()->toW3cString(),
            ];
        });

        $urlsArr = $urls->toArray();
        $this->writeSitemap($filename, $urlsArr);
        $this->generatedFiles[] = [
            'filename' => $filename,
            'lastmod' => $this->maxLastmodForUrls($urlsArr),
            'lang' => $lang,
        ];
        $this->line("  {$filename}: {$urls->count()} URLs");
    }

    private function generateSitemapIndex(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($this->generatedFiles as $file) {
            $xml .= '  <sitemap>'."\n";
            $xml .= '    <loc>'.$this->baseUrlForLanguage($file['lang'] ?? 'ru').'/'.$file['filename'].'</loc>'."\n";
            $xml .= '    <lastmod>'.$file['lastmod'].'</lastmod>'."\n";
            $xml .= '  </sitemap>'."\n";
        }

        $xml .= '</sitemapindex>';

        file_put_contents(public_path('sitemap.xml'), $xml);
        $this->info('  sitemap.xml (index): '.count($this->generatedFiles).' sitemaps');
    }

    private function writeSitemap(string $filename, array $urls): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= '  <url>'."\n";
            $xml .= '    <loc>'.htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8').'</loc>'."\n";
            $xml .= '    <lastmod>'.$url['lastmod'].'</lastmod>'."\n";
            $xml .= '  </url>'."\n";
        }

        $xml .= '</urlset>';

        file_put_contents(public_path($filename), $xml);
    }

    /**
     * @param  array<int, array{
     *     loc: string,
     *     lastmod: string,
     *     image_url: ?string,
     *     is_news: bool,
     *     news_publication_name: string,
     *     news_language: string,
     *     news_publication_date: string,
     *     news_title: string
     * }>  $entries
     */
    private function writePostSitemap(string $filename, array $entries, bool $includeGoogleNewsElements = false): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
            .' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
        if ($includeGoogleNewsElements) {
            $xml .= ' xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"';
        }
        $xml .= '>'."\n";

        foreach ($entries as $e) {
            $loc = htmlspecialchars(self::xmlSafeString($e['loc']), ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $lastmod = htmlspecialchars(self::xmlSafeString($e['lastmod']), ENT_XML1 | ENT_QUOTES, 'UTF-8');

            $xml .= '  <url>'."\n";
            $xml .= '    <loc>'.$loc.'</loc>'."\n";
            $xml .= '    <lastmod>'.$lastmod.'</lastmod>'."\n";

            if (! empty($e['image_url'])) {
                $img = htmlspecialchars(self::xmlSafeString($e['image_url']), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= '    <image:image>'."\n";
                $xml .= '      <image:loc>'.$img.'</image:loc>'."\n";
                $xml .= '    </image:image>'."\n";
            }

            if ($includeGoogleNewsElements && $e['is_news']) {
                $name = htmlspecialchars(self::xmlSafeString($e['news_publication_name']), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $nlang = htmlspecialchars(self::xmlSafeString($e['news_language']), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $pdate = htmlspecialchars(self::xmlSafeString($e['news_publication_date']), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $title = htmlspecialchars(self::xmlSafeString($e['news_title']), ENT_XML1 | ENT_QUOTES, 'UTF-8');

                $xml .= '    <news:news>'."\n";
                $xml .= '      <news:publication>'."\n";
                $xml .= '        <news:name>'.$name.'</news:name>'."\n";
                $xml .= '        <news:language>'.$nlang.'</news:language>'."\n";
                $xml .= '      </news:publication>'."\n";
                $xml .= '      <news:publication_date>'.$pdate.'</news:publication_date>'."\n";
                $xml .= '      <news:title>'.$title.'</news:title>'."\n";
                $xml .= '    </news:news>'."\n";
            }

            $xml .= '  </url>'."\n";
        }

        $xml .= '</urlset>';

        file_put_contents(public_path($filename), $xml);
    }

    /**
     * Strip bytes forbidden in XML 1.0 text before escaping — avoids corrupt documents from stray control chars in DB.
     */
    private static function xmlSafeString(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '';
    }

    private function cleanupOldSitemaps(): void
    {
        $keepFiles = array_column($this->generatedFiles, 'filename');
        $keepFiles[] = 'sitemap.xml';

        $existingFiles = glob(public_path('sitemap*.xml'));
        foreach ($existingFiles as $file) {
            $basename = basename($file);
            if (! in_array($basename, $keepFiles)) {
                unlink($file);
                $this->line("  Removed old: {$basename}");
            }
        }
    }

    private function getPostPath(Post $post): string
    {
        $columnistPrefix = ($post->type === PostTypes::OPINION && $post->columnist)
            ? "{$post->columnist->slug}/"
            : '';

        return '/'
            .($post->language_code === 'ru'
                ? "{$post->category->slug}/"
                : "{$post->language_code}/{$post->category->slug}/")
            .$columnistPrefix
            .$post->slug;
    }

    /**
     * @param  array<int, array{loc: string, lastmod: string}>  $urls
     */
    private function maxLastmodForUrls(array $urls): string
    {
        $max = null;
        foreach ($urls as $url) {
            $t = Carbon::parse($url['lastmod']);
            if ($max === null || $t->gt($max)) {
                $max = $t;
            }
        }

        return $max->toW3cString();
    }

    /**
     * Max `posts.updated_at` per category among published posts in this language.
     *
     * @return Collection<int, object{max_updated: mixed}>
     */
    private function latestPublishedPostMaxUpdatedAtByCategory(string $lang): Collection
    {
        return Post::query()
            ->where('status', Post::STATUS_PUBLISHED)
            ->where('language_code', $lang)
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->selectRaw('category_id, MAX(updated_at) as max_updated')
            ->get()
            ->keyBy('category_id');
    }

    /**
     * Max `posts.updated_at` per tag among published posts in this language.
     *
     * @return Collection<int, object{max_updated: mixed}>
     */
    private function latestPublishedPostMaxUpdatedAtByTag(string $lang): Collection
    {
        return DB::table('post_tags')
            ->join('posts', 'posts.id', '=', 'post_tags.post_id')
            ->where('posts.status', Post::STATUS_PUBLISHED)
            ->where('posts.language_code', $lang)
            ->groupBy('post_tags.tag_id')
            ->select('post_tags.tag_id', DB::raw('MAX(posts.updated_at) as max_updated'))
            ->get()
            ->keyBy('tag_id');
    }

    /**
     * Max `posts.updated_at` per author (post_authors) — same post types as on the author sitemap.
     *
     * @return Collection<int, object{max_updated: mixed}>
     */
    private function latestPublishedPostMaxUpdatedAtByAuthor(string $lang): Collection
    {
        return DB::table('post_authors')
            ->join('posts', 'posts.id', '=', 'post_authors.post_id')
            ->where('posts.status', Post::STATUS_PUBLISHED)
            ->where('posts.language_code', $lang)
            ->whereIn('posts.type', [PostTypes::ARTICLE, PostTypes::NEWS, PostTypes::ONLINE])
            ->groupBy('post_authors.author_id')
            ->select('post_authors.author_id', DB::raw('MAX(posts.updated_at) as max_updated'))
            ->get()
            ->keyBy('author_id');
    }

    /**
     * Max `posts.updated_at` per columnist among published opinion posts in this language.
     *
     * @return Collection<int, object{max_updated: mixed}>
     */
    private function latestPublishedPostMaxUpdatedAtByColumnist(string $lang): Collection
    {
        return Post::query()
            ->where('status', Post::STATUS_PUBLISHED)
            ->where('language_code', $lang)
            ->where('type', PostTypes::OPINION)
            ->whereNotNull('columnist_id')
            ->groupBy('columnist_id')
            ->selectRaw('columnist_id, MAX(updated_at) as max_updated')
            ->get()
            ->keyBy('columnist_id');
    }

    private function w3cLastmodFromMaxPostUpdated(mixed $maxUpdated, ?Carbon $fallback): string
    {
        if ($maxUpdated !== null && $maxUpdated !== '') {
            return Carbon::parse($maxUpdated)->toW3cString();
        }

        return ($fallback ?? now())->toW3cString();
    }

    private function baseUrlForLanguage(string $lang): string
    {
        $host = $lang === 'en'
            ? config('app.en_canonical_host')
            : config('app.ru_canonical_host');

        return rtrim((string) $host, '/');
    }
}
