<?php

namespace App\Services;

use App\Enums\PostTypes;
use App\Models\Post;
use App\Models\User;
use App\Nova\_Posts\PostArticle;
use App\Nova\_Posts\PostNews;
use App\Nova\_Posts\PostOnline;
use App\Nova\_Posts\PostOpinion;
use Illuminate\Support\Str;
use Laravel\Nova\Notifications\NovaNotification;

class PostViewMilestoneNovaNotifier
{
    /** @var list<int> */
    public const MILESTONES = [1000, 2000, 5000, 10000, 25000, 50000, 100000, 200000, 300000, 400000, 500000, 600000, 700000, 800000, 900000, 1000000];

    /** @var array<string, class-string> */
    private const TYPE_TO_NOVA_RESOURCE = [
        PostTypes::ARTICLE => PostArticle::class,
        PostTypes::NEWS => PostNews::class,
        PostTypes::OPINION => PostOpinion::class,
        PostTypes::ONLINE => PostOnline::class,
    ];

    public function notifyForCountIncrease(Post $post, int $viewsBefore, int $viewsAfter): void
    {
        if ($viewsAfter <= $viewsBefore) {
            return;
        }

        foreach (self::MILESTONES as $milestone) {
            if ($viewsBefore < $milestone && $viewsAfter >= $milestone) {
                $this->notifyMilestone($post, $milestone);
            }
        }
    }

    private function notifyMilestone(Post $post, int $milestone): void
    {
        $owners = $post->relationLoaded('owners')
            ? $post->owners
            : $post->owners()->get();

        if ($owners->isEmpty()) {
            return;
        }

        $resourceClass = self::TYPE_TO_NOVA_RESOURCE[$post->type] ?? null;
        if ($resourceClass === null) {
            return;
        }

        $uriKey = $resourceClass::uriKey();
        $path = '/resources/'.$uriKey.'/'.$post->id.'/edit';
        if ($post->type !== PostTypes::ONLINE) {
            $path .= '?nova_tab=content';
        }

        $locale = $this->localeFromPostLanguage($post);

        foreach ($owners->unique('id') as $user) {
            if (! $user instanceof User) {
                continue;
            }

            $countLabel = $this->formatViewCountForLocale($milestone, $locale);
            $title = $this->formatTitleForMessage($post, $locale);
            $message = trans('post_view_milestone.body', [
                'title' => $title,
                'count' => $countLabel,
            ], $locale);

            $user->notify(
                NovaNotification::make()
                    ->message($message)
                    ->type(NovaNotification::SUCCESS_TYPE)
                    ->action(trans('post_view_milestone.open_edit', [], $locale), $path)
            );
        }
    }

    private function localeFromPostLanguage(Post $post): string
    {
        $code = strtolower(trim((string) ($post->language_code ?? '')));

        return $code !== '' ? $code : (string) config('app.locale', 'ru');
    }

    /**
     * Avoid Illuminate\Support\Number::format() here — it requires ext-intl, which may be missing in some runtimes.
     */
    private function formatViewCountForLocale(int $milestone, string $locale): string
    {
        if (str_starts_with(strtolower($locale), 'ru')) {
            return number_format($milestone, 0, ',', ' ');
        }

        return number_format($milestone);
    }

    private function formatTitleForMessage(Post $post, string $locale): string
    {
        $raw = trim((string) ($post->title ?? ''));
        if ($raw === '') {
            return trans('post_view_milestone.untitled', [], $locale);
        }

        return Str::limit($raw, 200);
    }
}
