<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Author;
use App\Models\InvestigationTheme;
use App\Enums\PostTypes;
use Carbon\Carbon;

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
            $this->generateAuthorSitemap($lang);
            $this->generateColumnistSitemap($lang);
        }

        $this->generateSitemapIndex();
        $this->cleanupOldSitemaps();

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->info("Done! Generated " . count($this->generatedFiles) . " sitemap files in {$elapsed}s");

        return Command::SUCCESS;
    }

    private function generatePostSitemaps(string $lang): void
    {
        $chunkIndex = 0;

        Post::where('status', Post::STATUS_PUBLISHED)
            ->where('language_code', $lang)
            ->with(['category', 'columnist'])
            ->orderBy('published_at', 'desc')
            ->chunk(self::POSTS_PER_FILE, function ($posts) use ($lang, &$chunkIndex) {
                $chunkIndex++;
                $filename = "sitemap-posts-{$lang}-{$chunkIndex}.xml";

                $urls = $posts->map(function (Post $post) use ($lang) {
                    $path = $this->getPostPath($post);
                    $priority = $this->getPostPriority($post);
                    $changefreq = $this->getChangeFreq($post->published_at);

                    return [
                        'loc' => $this->baseUrlForLanguage($lang) . $path,
                        'lastmod' => $post->updated_at->toW3cString(),
                        'changefreq' => $changefreq,
                        'priority' => $priority,
                    ];
                });

                $lastmod = $posts->max('updated_at');
                $this->writeSitemap($filename, $urls->toArray());
                $this->generatedFiles[] = ['filename' => $filename, 'lastmod' => $lastmod->toW3cString(), 'lang' => $lang];

                $this->line("  {$filename}: {$urls->count()} URLs");
            });
    }

    private function generateCategorySitemap(string $lang): void
    {
        $homePath = $lang === 'ru' ? '/' : "/{$lang}";
        $baseUrl = $this->baseUrlForLanguage($lang);
        $urls = collect([[
            'loc' => $baseUrl . $homePath,
            'lastmod' => now()->toW3cString(),
            'changefreq' => 'always',
            'priority' => '1.0',
        ]]);

        $categories = Category::where('language_code', $lang)
            ->where('is_show_in_menu', true)
            ->get();
        $categoryUrls = $categories->map(function (Category $category) use ($lang) {
            $path = '/' . ($lang === 'ru' ? '' : "{$lang}/") . $category->slug;
            $isNews = $category->type === PostTypes::NEWS;
            return [
                'loc' => $baseUrl . $path,
                'lastmod' => $category->updated_at?->toW3cString() ?? now()->toW3cString(),
                'changefreq' => $isNews ? 'always' : 'hourly',
                'priority' => $isNews ? '0.9' : '0.7',
            ];
        });

        $urls = $urls->merge($categoryUrls);

        $filename = "sitemap-pages-{$lang}.xml";
        $this->writeSitemap($filename, $urls->toArray());
        $this->generatedFiles[] = ['filename' => $filename, 'lastmod' => now()->toW3cString(), 'lang' => $lang];
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
        $urls = $tags->map(function (Tag $tag) use ($lang, $baseUrl) {
            $path = '/' . ($lang === 'ru' ? '' : "{$lang}/") . "tag/{$tag->slug}";
            return [
                'loc' => $baseUrl . $path,
                'lastmod' => $tag->updated_at?->toW3cString() ?? now()->toW3cString(),
                'changefreq' => 'weekly',
                'priority' => '0.4',
            ];
        });

        $this->writeSitemap($filename, $urls->toArray());
        $this->generatedFiles[] = ['filename' => $filename, 'lastmod' => now()->toW3cString(), 'lang' => $lang];
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
        $urls = $authors->map(function (Author $author) use ($lang, $baseUrl) {
            $path = '/' . ($lang === 'ru' ? '' : "{$lang}/") . "authors/{$author->slug}";
            return [
                'loc' => $baseUrl . $path,
                'lastmod' => $author->updated_at?->toW3cString() ?? now()->toW3cString(),
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ];
        });

        $this->writeSitemap($filename, $urls->toArray());
        $this->generatedFiles[] = ['filename' => $filename, 'lastmod' => now()->toW3cString(), 'lang' => $lang];
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
        $urls = $columnists->map(function (Author $author) use ($lang, $baseUrl) {
            $path = '/' . ($lang === 'ru' ? '' : "{$lang}/") . "opinions/{$author->slug}";
            return [
                'loc' => $baseUrl . $path,
                'lastmod' => $author->updated_at?->toW3cString() ?? now()->toW3cString(),
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ];
        });

        $this->writeSitemap($filename, $urls->toArray());
        $this->generatedFiles[] = ['filename' => $filename, 'lastmod' => now()->toW3cString(), 'lang' => $lang];
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
                'loc' => $baseUrl . $theme->getPath(),
                'lastmod' => $theme->updated_at?->toW3cString() ?? now()->toW3cString(),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ];
        });

        $this->writeSitemap($filename, $urls->toArray());
        $this->generatedFiles[] = ['filename' => $filename, 'lastmod' => now()->toW3cString(), 'lang' => $lang];
        $this->line("  {$filename}: {$urls->count()} URLs");
    }

    private function generateSitemapIndex(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($this->generatedFiles as $file) {
            $xml .= '  <sitemap>' . "\n";
            $xml .= '    <loc>' . $this->baseUrlForLanguage($file['lang'] ?? 'ru') . '/' . $file['filename'] . '</loc>' . "\n";
            $xml .= '    <lastmod>' . $file['lastmod'] . '</lastmod>' . "\n";
            $xml .= '  </sitemap>' . "\n";
        }

        $xml .= '</sitemapindex>';

        file_put_contents(public_path('sitemap.xml'), $xml);
        $this->info('  sitemap.xml (index): ' . count($this->generatedFiles) . ' sitemaps');
    }

    private function writeSitemap(string $filename, array $urls): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8') . '</loc>' . "\n";
            $xml .= '    <lastmod>' . $url['lastmod'] . '</lastmod>' . "\n";
            $xml .= '    <changefreq>' . $url['changefreq'] . '</changefreq>' . "\n";
            $xml .= '    <priority>' . $url['priority'] . '</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';

        file_put_contents(public_path($filename), $xml);
    }

    private function cleanupOldSitemaps(): void
    {
        $keepFiles = array_column($this->generatedFiles, 'filename');
        $keepFiles[] = 'sitemap.xml';

        $existingFiles = glob(public_path('sitemap*.xml'));
        foreach ($existingFiles as $file) {
            $basename = basename($file);
            if (!in_array($basename, $keepFiles)) {
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
            . ($post->language_code === 'ru'
                ? "{$post->category->slug}/"
                : "{$post->language_code}/{$post->category->slug}/")
            . $columnistPrefix
            . $post->slug;
    }

    private function getPostPriority(Post $post): string
    {
        if ($post->is_super_news) {
            return '1.0';
        }

        return match ($post->type) {
            PostTypes::ARTICLE => '0.8',
            PostTypes::NEWS => '0.6',
            PostTypes::OPINION => '0.7',
            default => '0.5',
        };
    }

    private function getChangeFreq(?Carbon $publishedAt): string
    {
        if (!$publishedAt) {
            return 'monthly';
        }

        $daysAgo = $publishedAt->diffInDays(now());

        if ($daysAgo < 2) {
            return 'hourly';
        }
        if ($daysAgo < 7) {
            return 'daily';
        }
        if ($daysAgo < 30) {
            return 'weekly';
        }

        return 'monthly';
    }

    private function baseUrlForLanguage(string $lang): string
    {
        $host = $lang === 'en'
            ? config('app.en_canonical_host')
            : config('app.ru_canonical_host');

        return rtrim((string) $host, '/');
    }
}
