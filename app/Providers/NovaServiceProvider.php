<?php

namespace App\Providers;

use App\Http\Controllers\Nova\AttachedResourceUpdateController as AppAttachedResourceUpdateController;
use App\Http\Controllers\Nova\ResourceUpdateController as AppResourceUpdateController;
use App\Models\User;
use App\Support\Nova\SidebarMenuGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Http\Controllers\AttachedResourceUpdateController as NovaAttachedResourceUpdateController;
use Laravel\Nova\Http\Controllers\ResourceUpdateController as NovaResourceUpdateController;
use Laravel\Nova\Menu\Menu;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;
use Medov\NewsCalendar\NewsCalendar;
use Medov\PostHistory\PostHistory;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Только в ServingNova: иначе первый provideToScript в boot() инициализирует jsonVariables
        // с userId = null и меню навсегда пустое (см. Nova::provideToScript при empty $jsonVariables).
        Nova::serving(function () {
            Nova::provideToScript([
                'frontendPublicUrl' => fn () => rtrim((string) config('app.frontend_url'), '/'),
            ]);
        });

        Nova::withoutThemeSwitcher('light');
        Nova::withoutGlobalSearch();
        Nova::initialPath('/dashboards/content-dashboard');

        Nova::style('nova-rfdewi', asset('css/nova-rfdewi.css'));
        Nova::style('custom-nova', asset('css/custom-nova.css'));
        Nova::style('nova-sidebar-menu', asset('css/nova-sidebar-menu.css'));
        Nova::style('nova-app-widgets', asset('css/nova-app-widgets.css'));
        Nova::script('custom-nova-scripts', asset('js/nova-custom.js'));
        Nova::script('nova-sidebar-menu', asset('js/nova-sidebar-menu.js'));
        Nova::style('nova-form-action-bar', asset('css/nova-form-action-bar.css'));
        Nova::script('nova-form-action-bar', asset('js/nova-form-action-bar.js'));

        Nova::mainMenu(function ($request) {

            $menu = [

                SidebarMenuGroup::make(__('Analytics'), [
                    MenuItem::dashboard(\App\Nova\Dashboards\ContentDashboard::class),
                    MenuItem::link(__('News calendar'), '/news-calendar')
                        ->canSee(fn ($request) => ! $request->user()?->isJournalist()),
                ])->collapsable()->persistCollapseKey('analytics'),

                SidebarMenuGroup::make(__('Posts'), [
                    MenuItem::resource(\App\Nova\_Posts\PostArticle::class),
                    MenuItem::resource(\App\Nova\_Posts\PostNews::class),
                    MenuItem::resource(\App\Nova\_Posts\PostOpinion::class),
                    MenuItem::resource(\App\Nova\_Posts\PostOnline::class),
                    MenuItem::resource(\App\Nova\_Posts\OnlineMessage::class),
                ])->collapsable()->collapsedByDefault()->persistCollapseKey('posts'),

                SidebarMenuGroup::make(__('Main page'), [
                    MenuItem::resource(\App\Nova\_Collections\CollectionFeature::class),
                    MenuItem::resource(\App\Nova\_Collections\CollectionPopular::class),
                    MenuItem::resource(\App\Nova\_Collections\CollectionTopNews::class),
                    MenuItem::resource(\App\Nova\_Collections\CollectionMainOpinions::class),
                ])->collapsable()->collapsedByDefault()->persistCollapseKey('main-page'),

                SidebarMenuGroup::make(__('Taxonomy'), [
                    MenuItem::resource(\App\Nova\_Taxonomy\Category::class),
                    MenuItem::resource(\App\Nova\_Taxonomy\InvestigationTheme::class),
                    MenuItem::resource(\App\Nova\_Taxonomy\Tag::class),
                    MenuItem::resource(\App\Nova\_Taxonomy\Termin::class),
                    MenuItem::resource(\App\Nova\_Taxonomy\Author::class),
                ])->collapsable()->collapsedByDefault()->persistCollapseKey('taxonomy'),

                SidebarMenuGroup::make(__('Users'), [
                    MenuItem::resource(\App\Nova\_Users\User::class),
                    MenuItem::resource(\App\Nova\_Users\UserAdmin::class),
                    MenuItem::resource(\App\Nova\_Users\UserEditor::class),
                    MenuItem::resource(\App\Nova\_Users\UserJournalist::class),
                ])->collapsable()->collapsedByDefault()->persistCollapseKey('users'),

            ];

            foreach (auth()->user()?->available_languages ?? [] as $language => $active) {
                if ($active == true && ! app()->isLocale($language)) {
                    $localeSwitchLabel = match ($language) {
                        'en' => __('Admin sidebar: Switch to English'),
                        'ru' => __('Admin sidebar: Switch to Russian'),
                        default => __('Admin sidebar: Switch to language', ['locale' => strtoupper($language)]),
                    };
                    $menu = array_merge([
                        MenuItem::externalLink($localeSwitchLabel, '/set-locale/'.$language),
                    ], $menu);
                }
            }

            return $menu;
        });

        Nova::userMenu(function (Request $request, Menu $menu) {
            if (! $request->user()) {
                return $menu;
            }

            return $menu->prepend(
                MenuItem::link(__('User settings'), '/resources/users/'.$request->user()->getKey().'/edit')
            );
        });

        Nova::footer(function (Request $request) {
            return Blade::render(__('THE INSIDER · REPORTS. ANALYTICS. INVESTIGATIONS.'));
        });

        Nova::serving(function () {
            $path = base_path('nova-components/CustomFlexibleMenu/dist/js/flexible-form-override.js');
            if (File::exists($path)) {
                Nova::script('flexible-form-override', $path);
            }

            $imageCropperDndScriptPath = base_path('nova-components/ImageCropperDnd/dist/js/nova-image-cropper-dnd.js');
            $imageCropperDndStylePath = base_path('nova-components/ImageCropperDnd/dist/css/nova-image-cropper-dnd.css');
            if (File::exists($imageCropperDndScriptPath)) {
                Nova::script('nova-image-cropper-dnd', $imageCropperDndScriptPath);
            }
            if (File::exists($imageCropperDndStylePath)) {
                Nova::style('nova-image-cropper-dnd', $imageCropperDndStylePath);
            }
        });
    }

    /**
     * Register the configurations for Laravel Fortify.
     */
    protected function fortify(): void
    {
        Nova::fortify()
            ->features([
                // Features::updatePasswords(),
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
            // Разрешаем доступ всем авторизованным пользователям
            return true;
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

        $this->app->bind(NovaResourceUpdateController::class, AppResourceUpdateController::class);
        $this->app->bind(NovaAttachedResourceUpdateController::class, AppAttachedResourceUpdateController::class);
    }
}
