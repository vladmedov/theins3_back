<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

use App\Models\Category;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryCollection;

class CategoryController extends Controller
{
    public function getCategories($language_code)
    {
        $categories = Category::where('language_code', $language_code)->get();
        return CategoryResource::collection($categories);
    }

    public function getCategory($language_code, $slug)
    {
        $category = Category
            ::with(['posts' => function($query) {
                $query->with(['category', 'authors', 'columnist'])->orderBy('published_at', 'desc')->paginate(36);
            }])
            ->where('language_code', $language_code)
            ->where('slug', $slug)
            ->firstOrFail();

        return new CategoryResource($category, false);
    }

    
}