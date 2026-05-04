<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardReportRequest;
use App\Queries\Reports\GetDashboardReportDataQuery;
use App\Support\Reports\DashboardReportExport;
use Illuminate\Http\Response;

class DashboardExportController extends Controller
{
    public function __invoke(
        string $format,
        DashboardReportRequest $request,
        GetDashboardReportDataQuery $getDashboardReportDataQuery,
        DashboardReportExport $dashboardReportExport
    ): Response {
        abort_unless(in_array($format, ['csv', 'xls'], true), 404);

        $filters = $request->reportFilters();
        $report = $getDashboardReportDataQuery->handle($request->user(), $filters);
        $sections = $dashboardReportExport->sections($report);
        $filename = $dashboardReportExport->filename($request->user(), $filters, $format);

        return $format === 'csv'
            ? $dashboardReportExport->csvResponse($sections, $filename)
            : $dashboardReportExport->xlsResponse($sections, $filename, $report);
    }
}
