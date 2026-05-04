<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Queries\Management\SearchManagementEntitiesQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request, SearchManagementEntitiesQuery $searchManagementEntitiesQuery): View
    {
        $search = trim((string) $request->query('q', ''));
        $results = $searchManagementEntitiesQuery->handle($request->user(), $search);

        return view('manage.search.index', [
            'search' => $search,
            'results' => $results,
            'totalResults' => collect($results)->sum(fn ($items) => $items->count()),
        ]);
    }
}
