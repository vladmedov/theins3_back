<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

use App\Models\Author;
use App\Models\Post;
use App\Enums\PostTypes;

use App\Http\Resources\AuthorResource;
use App\Http\Resources\ColumnistResource;

class UserController extends Controller
{
    public function getAuthor($language_code, $slug)
    {
        $author = Author::where('language_code', $language_code)
            ->where('slug', $slug)
            ->where('is_author_page_hidden', false)
            ->whereExists(function ($query) use ($language_code) {
                $query->select(DB::raw(1))
                    ->from('posts')
                    ->join('post_authors', 'posts.id', '=', 'post_authors.post_id')
                    ->whereColumn('post_authors.author_id', 'authors.id')
                    ->where('posts.status', Post::STATUS_PUBLISHED)
                    ->where('posts.language_code', $language_code)
                    ->whereIn('posts.type', [PostTypes::ARTICLE, PostTypes::NEWS, PostTypes::ONLINE]);
            })
            ->firstOrFail();

        return new AuthorResource($author, false);
    }

    public function getColumnist($language_code, $slug)
    {
        $columnist = Author::where('language_code', $language_code)
            ->where('slug', $slug)
            ->where('is_columnist_page_hidden', false)
            ->whereExists(function ($query) use ($language_code) {
                $query->select(DB::raw(1))
                    ->from('posts')
                    ->whereColumn('posts.columnist_id', 'authors.id')
                    ->where('posts.status', Post::STATUS_PUBLISHED)
                    ->where('posts.type', PostTypes::OPINION)
                    ->where('posts.language_code', $language_code);
            })
            ->firstOrFail();

        return new ColumnistResource($columnist, false);
    }
}
