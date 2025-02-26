<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Config;

class LocaleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->user()) {
            $locale = $request->session()->get('locale');
            if (isset(auth()->user()->available_languages[$locale]) && auth()->user()->available_languages[$locale] == true) {
                App::setLocale($locale);
            } else {
                App::setLocale(
                    collect(auth()->user()->available_languages)
                        ->filter(function ($enabled) {
                            return $enabled === true;
                        })
                        ->keys()
                        ->first()
                );
            }
        }

        if (!App::isLocale('ru') && !App::isLocale('en')) {
            App::setLocale('ru');
        }
        
        return $next($request);
    }
}
