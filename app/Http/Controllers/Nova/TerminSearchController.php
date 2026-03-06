<?php

namespace App\Http\Controllers\Nova;

use App\Http\Controllers\Controller;
use App\Models\Termin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'language_code' => $request->input('language_code', 'ru'),
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

        $termins = Termin::query()
            ->when($query !== '', fn ($q) => $q->where('termin', 'like', "%{$query}%"))
            ->orderBy('termin')
            ->limit(20)
            ->get(['id', 'termin', 'description']);

        return response()->json($termins);
    }
}
