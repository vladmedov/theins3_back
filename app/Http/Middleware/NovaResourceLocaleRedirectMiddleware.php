<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Laravel\Nova\Nova;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets app locale from `language_code` on the Nova resource row being opened.
 */
class NovaResourceLocaleRedirectMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Nova::resourceCollection()->isEmpty()) {
            Nova::resourcesIn(app_path('Nova'));
        }

        $r = $request->route()?->parameters() ?? [];
        $key = $r['resource'] ?? $request->input('resourceName') ?? $request->input('viaResource');
        $id = $r['resourceId'] ?? $request->input('resourceId') ?? $request->input('viaResourceId');

        if (! $key || ! $this->digitsId($id)) {
            [$key, $id] = $this->fromPath($request->path(), $key, $id);
        }

        $model = $this->model($key, $id);
        if ($model?->language_code) {
            app()->setLocale($model->language_code);
        }

        return $next($request);
    }

    protected function model(?string $key, mixed $id): ?Model
    {
        if (! $key || ! $this->digitsId($id)) {
            return null;
        }
        $class = Nova::resourceForKey($key);

        return $class ? $class::newModel()->newQuery()->find($id) : null;
    }

    /** …/resources|nova-api/{key}/{numericId}/… — не путаем с …/soft-deletes и т.п. */
    protected function fromPath(string $path, ?string $key, mixed $id): array
    {
        $p = array_values(array_filter(explode('/', trim($path, '/'))));
        foreach (['resources', 'nova-api'] as $mark) {
            $i = array_search($mark, $p, true);
            if ($i === false || ! isset($p[$i + 1], $p[$i + 2])) {
                continue;
            }
            $key = $key ?: $p[$i + 1];
            if (! $this->digitsId($id) && $this->digitsId($p[$i + 2])) {
                $id = $p[$i + 2];
            }
            break;
        }

        return [$key, $id];
    }

    protected function digitsId(mixed $v): bool
    {
        return $v !== null && $v !== '' && preg_match('/^\d+$/', (string) $v) === 1;
    }
}
