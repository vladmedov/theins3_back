<?php

namespace App\Nova\_Collections;

use App\Enums\CategoryTypes;
use App\Enums\PostTypes;
use App\Nova\_Posts\Post;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;

class PostCollection extends Post
{
    public static $model = 'App\Models\Post';

    public static $globallySearchable = false;

    public static $title = 'title';
    public static $search = ['id', 'title', 'slug'];

    /**
     * BelongsTo к PostCollection задаёт язык и тип в relatableQueryUsing на стороне поля.
     * Post::indexQuery добавляет language_code текущей записи — для «Перевод» это тот же язык,
     * что и у редактируемого поста, а поле требует другой язык → поиск всегда пустой.
     */
    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        if (! $request->user()->isAdmin() && ! $request->user()->isEditor()) {
            $query->whereHas('owners', function ($q) {
                $q->where('user_id', auth()->user()->id);
            });
        }

        return $query->with('category');
    }

    public function subtitle()
    {
        $formattedDate = $this->published_at ? $this->published_at->format('d.m.Y H:i:s') : '';
        $categoryTitle = $this->category?->title;
        $subtitle = $formattedDate;

        if (CategoryTypes::isDefault($this->type) && $categoryTitle) {
            $subtitle = $formattedDate !== '' ? "{$categoryTitle} / {$formattedDate}" : $categoryTitle;
        }

        return "[" . PostTypes::get($this->type) . "] " . $subtitle;
    }
}
