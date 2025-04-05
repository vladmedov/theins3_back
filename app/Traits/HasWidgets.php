<?php

namespace App\Traits;

use App\Models\Post;
use App\Models\Author;
use App\Enums\PostTypes;
use App\Http\Resources\PostResource;
use App\Http\Resources\ColumnistResource;

trait HasWidgets
{
    private function getWidgets(): array
    {
        return [
            [
                'type' => 'posts_list',
                'attributes' => [
                    'title' => 'Главные новости',
                    'posts' => PostResource::collection(Post::where('type', PostTypes::NEWS)->with(['category', 'authors'])->where('language_code', 'ru')->where('type', PostTypes::NEWS)->limit(2)->get())
                ],
            ],
            [
                'type' => 'posts_list',
                'attributes' => [
                    'title' => 'Мнения',
                    'posts' => PostResource::collection(Post::where('type', PostTypes::OPINION)->with(['category', 'columnist'])->where('language_code', 'ru')->limit(2)->get())
                ],
            ],
            [
                'type' => 'columnists_list',
                'attributes' => [
                    'title' => 'Колумнисты',
                    'users' => ColumnistResource::collection(Author::whereJsonContains('allowed_post_types', [PostTypes::OPINION])->limit(2)->get())
                ],
            ],
            [
                'type' => 'donations',
                'attributes' => [],
            ],
            [
                'type' => 'subscribe',
                'attributes' => [],
            ],
            [
                'type' => 'social',
                'attributes' => [],
            ],
        ];
    }
} 