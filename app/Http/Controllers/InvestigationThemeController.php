<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

use App\Models\InvestigationTheme;
use App\Http\Resources\InvestigationThemeResource;

class InvestigationThemeController extends Controller
{
    public function getInvestigationThemes($language_code = 'ru')
    {
        $investigationThemes = InvestigationTheme::where('language_code', $language_code)
            ->orderBy('position', 'ASC')
            ->get();
        return InvestigationThemeResource::collection($investigationThemes);
    }

    public function getInvestigationTheme($language_code, $slug)
    {
        $investigationTheme = InvestigationTheme
            ::with(['posts' => function($query) {
                $query->with(['category', 'authors', 'columnist'])->orderBy('published_at', 'DESC')->paginate(36);
            }])
            ->where('language_code', $language_code)
            ->where('slug', $slug)
            ->firstOrFail();

        return new InvestigationThemeResource($investigationTheme, false);
    }
}