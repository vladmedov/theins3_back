<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

use App\Models\Author;
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
            // ->where(function($query) {
            //     $query
            //         ->whereJsonContains('allowed_post_types', PostTypes::ARTICLE)
            //         ->orWhereJsonContains('allowed_post_types', PostTypes::NEWS)
            //         ->orWhereJsonContains('allowed_post_types', PostTypes::ONLINE);
            // })
            ->firstOrFail();

        return new AuthorResource($author, false);
    }

    public function getColumnist($language_code, $slug)
    {
        $columnist = Author::where('language_code', $language_code)
            ->where('slug', $slug)
            ->where('is_columnist_page_hidden', false)
            // ->whereJsonContains('allowed_post_types', PostTypes::OPINION)
            ->firstOrFail();
        return new ColumnistResource($columnist, false);
    }

    
}