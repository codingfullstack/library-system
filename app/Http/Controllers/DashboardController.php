<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardReportRequest;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardReportRequest $request): View
    {
        return view('dashboard', [
            'filters' => $request->reportFilters(),
        ]);
    }
}
