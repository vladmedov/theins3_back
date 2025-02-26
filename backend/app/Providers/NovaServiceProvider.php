<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

use Laravel\Fortify\Features;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;

use Laravel\Nova\Menu\Dashboards\Dashboard;
use Laravel\Nova\Menu\Menu;
use Laravel\Nova\Menu\MenuGroup;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuSection;

use Medov\NewsCalendar\NewsCalendar;
use Medov\PostHistory\PostHistory;

use App\Nova\Filters\CategoryFilter;
use App\Nova\_Posts\PostArticle;

use App\Models\User;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Nova::withoutThemeSwitcher('light');
        Nova::withoutGlobalSearch();
        Nova::initialPath('/dashboards/content-dashboard');

        Nova::style('custom-nova', asset('css/custom-nova.css'));
        Nova::script('custom-nova-scripts', asset('js/nova-custom.js'));
        
        Nova::mainMenu(function ($request) {
            $menu = [
                
                MenuGroup::make(__('Analytics'), [
                    MenuItem::dashboard(\App\Nova\Dashboards\ContentDashboard::class),
                    MenuItem::link(__('News calendar'), '/news-calendar'),
                ])->collapsable(),//->icon('chart-pie'),

                MenuGroup::make(__('Posts'), [
                    MenuItem::resource(\App\Nova\_Posts\PostArticle::class),
                    MenuItem::resource(\App\Nova\_Posts\PostNews::class),
                    MenuItem::resource(\App\Nova\_Posts\PostOpinion::class),
                    MenuItem::resource(\App\Nova\_Posts\PostOnline::class),
                    MenuItem::resource(\App\Nova\_Posts\OnlineMessage::class),
                ])->collapsable(),//->icon('pencil'),

                MenuGroup::make(__('Main page'), [
                    MenuItem::resource(\App\Nova\_Collections\CollectionFeature::class),
                    MenuItem::resource(\App\Nova\_Collections\CollectionPopular::class),
                    MenuItem::resource(\App\Nova\_Collections\CollectionMainOpinions::class),
                ])->collapsable()->collapsedByDefault(),//->icon('users'),

                MenuGroup::make(__('Taxonomy'), [
                    MenuItem::resource(\App\Nova\_Taxonomy\Category::class),
                    MenuItem::resource(\App\Nova\_Taxonomy\InvestigationTheme::class),
                    MenuItem::resource(\App\Nova\_Taxonomy\Tag::class),
                    MenuItem::resource(\App\Nova\_Taxonomy\Termin::class),
                ])->collapsable()->collapsedByDefault(),//->icon('tag'),

                MenuGroup::make(__('Users'), [
                    MenuItem::resource(\App\Nova\_Users\User::class),
                    MenuItem::resource(\App\Nova\_Users\UserAdmin::class),
                    MenuItem::resource(\App\Nova\_Users\UserEditor::class),
                    MenuItem::resource(\App\Nova\_Users\UserAuthor::class),
                    MenuItem::resource(\App\Nova\_Users\UserNewsWriter::class),
                    MenuItem::resource(\App\Nova\_Users\UserColumnist::class),
                ])->collapsable()->collapsedByDefault(),//->icon('cog-6-tooth'),

            ];

            foreach (auth()->user()->available_languages as $language => $active) {
                if ($active == true && !app()->isLocale($language)) {
                    $menu[] = [
                        MenuItem::externalLink(strtoupper($language), '/set-locale/' . $language),
                    ];
                }
            }

            return $menu;
        });

        Nova::footer(function (Request $request) {
            return Blade::render(__('THE INSIDER · REPORTS. ANALYTICS. INVESTIGATIONS.'));
        });
    }

    /**
     * Register the configurations for Laravel Fortify.
     */
    protected function fortify(): void
    {
        Nova::fortify()
            ->features([
                Features::updatePasswords(),
                // Features::emailVerification(),
                // Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
            ])
            ->register();
    }

    /**
     * Register the Nova routes.
     */
    protected function routes(): void
    {
        Nova::routes()
            ->withAuthenticationRoutes(default: true)
            ->withPasswordResetRoutes()
            ->withoutEmailVerificationRoutes()
            ->register();
    }

    /**
     * Register the Nova gate.
     *
     * This gate determines who can access Nova in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewNova', function (User $user) {
            return !$user->is_authentication_disabled;

            return in_array($user->email, [
                //
            ]);
        });
    }

    /**
     * Get the dashboards that should be listed in the Nova sidebar.
     *
     * @return array<int, \Laravel\Nova\Dashboard>
     */
    protected function dashboards(): array
    {
        return [
            new \App\Nova\Dashboards\ContentDashboard,
        ];
    }

    /**
     * Get the tools that should be listed in the Nova sidebar.
     *
     * @return array<int, \Laravel\Nova\Tool>
     */
    public function tools()
    {
        return [
            new NewsCalendar,
            new PostHistory,
        ];
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        parent::register();

        //
    }
}
