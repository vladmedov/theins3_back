<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

use App\Models\User;
use App\Enums\UserRoles;

use App\Http\Resources\AuthorResource;
use App\Http\Resources\ColumnistResource;

class UserController extends Controller
{
    public function getAuthor($slug, $language_code = 'ru')
    {
        $author = User::where('slug', $slug)->where(function($query) {
            $query
                ->whereJsonContains('roles', UserRoles::ADMIN)
                ->orWhereJsonContains('roles', UserRoles::EDITOR)
                ->orWhereJsonContains('roles', UserRoles::AUTHOR)
                ->orWhereJsonContains('roles', UserRoles::NEWS_WRITER);
        })->firstOrFail();
        return new AuthorResource($author->setLocale($language_code), false);
    }

    public function getColumnist($slug, $language_code = 'ru')
    {
        $columnist = User::where('slug', $slug)->whereJsonContains('roles', UserRoles::COLUMNIST)->firstOrFail();
        return new ColumnistResource($columnist->setLocale($language_code), false);
    }

    
}