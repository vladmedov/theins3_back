<?php

namespace App\Http\Controllers\Nova;

use App\Http\Controllers\Controller;
use App\Models\Termin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Nova\Nova;

class TerminSearchController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'termin'      => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $termin = Termin::create([
            'termin'        => trim($request->input('termin')),
            'description'   => trim($request->input('description', '')),
            'language_code' => $this->resolveLanguageCode($request),
        ]);

        return response()->json($termin->only('id', 'termin', 'description'), 201);
    }

    public function search(Request $request): JsonResponse
    {
        // Single term by ID — used when opening modal on an existing termin span
        if ($request->filled('id')) {
            $termin = Termin::find((int) $request->get('id'), ['id', 'termin', 'description']);
            return response()->json($termin ? [$termin] : []);
        }

        $query = trim($request->get('q', ''));
        $languageCode = $this->resolveLanguageCode($request);

        $termins = Termin::query()
            ->where('language_code', $languageCode)
            ->when(
                $query !== '',
                fn ($q) => $q->whereRaw(
                    'LOWER(termin) LIKE ?',
                    ['%' . mb_strtolower($query, 'UTF-8') . '%'],
                ),
            )
            ->orderBy('termin')
            ->limit(20)
            ->get(['id', 'termin', 'description']);

        return response()->json($termins);
    }

    protected function resolveLanguageCode(Request $request): string
    {
        $explicit = trim((string) $request->input('language_code', ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $resourceKey = (string) $request->input('resourceName', $request->input('viaResource', ''));
        $resourceId = $request->input('resourceId', $request->input('viaResourceId'));

        if (
            $resourceKey !== ''
            && $resourceId !== null
            && preg_match('/^\d+$/', (string) $resourceId) === 1
        ) {
            $resourceClass = Nova::resourceForKey($resourceKey);
            if ($resourceClass) {
                $model = $resourceClass::newModel()->newQuery()->find((int) $resourceId);
                if ($model?->language_code) {
                    return (string) $model->language_code;
                }
            }
        }

        return app()->getLocale() ?: 'ru';
    }
}
