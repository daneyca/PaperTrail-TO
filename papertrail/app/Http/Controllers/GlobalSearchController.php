<?php

namespace App\Http\Controllers;

use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GlobalSearchController extends Controller
{
    public function index(Request $request, GlobalSearchService $search): View
    {
        $term = $this->term($request);

        return view('search.index', [
            'term' => $term,
            'results' => $search->search($request->user(), $term),
        ]);
    }

    public function suggestions(Request $request, GlobalSearchService $search): JsonResponse
    {
        $term = $this->term($request);

        return response()->json([
            'results' => $search->search($request->user(), $term, 5)->map(fn (array $result) => [
                'type' => $result['type'],
                'reference' => $result['reference'],
                'title' => $result['title'],
                'url' => $result['url'],
            ]),
        ]);
    }

    private function term(Request $request): string
    {
        $value = $request->query('q');

        return is_string($value) ? mb_substr(trim($value), 0, 100) : '';
    }
}
