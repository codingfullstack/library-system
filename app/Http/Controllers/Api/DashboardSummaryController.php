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
        $user = $request->user();

        if (! $user->isSuperAdmin() && ! $user->activeLibraryId()) {
            abort(403, 'Neturite aktyvios narystes pasirinktoje bibliotekoje.');
        }

        return response()->json([
            'summary' => $getDashboardReportDataQuery->summary($user, [
                'branch_id' => $request->query('branch_id'),
            ]),
        ]);
    }
}
