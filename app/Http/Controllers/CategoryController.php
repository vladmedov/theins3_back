<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

use App\Models\Category;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryCollection;

use App\Models\Post;

class CategoryController extends Controller
{
    public function getCategories($language_code)
    {
        $categories = Category::where('language_code', $language_code)
            ->where('is_show_in_menu', true)
            ->get();

        return CategoryResource::collection($categories);
    }

    public function getCategory($language_code, $slug)
    {
        $category = Category
            ::with(['posts' => function($query) {
                $query->with(['category', 'authors', 'columnist'])->where('status', Post::STATUS_PUBLISHED)->orderBy('published_at', 'desc')->paginate(36);
            }])
            ->where('language_code', $language_code)
            ->where('slug', $slug)
            ->where('is_show_in_menu', true)
            ->firstOrFail();

        return new CategoryResource($category, false);
    }

    
}