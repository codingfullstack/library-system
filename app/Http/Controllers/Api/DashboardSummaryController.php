<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Queries\Reports\GetDashboardReportDataQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardSummaryController extends Controller
{
    public function __invoke(Request $request, GetDashboardReportDataQuery $getDashboardReportDataQuery): JsonResponse
    {
        $dashboard = $getDashboardReportDataQuery->handle($request->user());

        return response()->json([
            'summary' => $dashboard['summary'],
        ]);
    }
}
