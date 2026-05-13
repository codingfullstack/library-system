<?php

namespace App\Http\Requests;

use App\Support\Reports\ResolveDashboardFilters;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in(['all', 'today', '7_days', '30_days', 'this_week', 'this_month', 'last_month', 'this_quarter', 'this_year', 'custom'])],
            'date_from' => ['nullable', 'date', 'required_if:period,custom'],
            'date_to' => ['nullable', 'date', 'required_if:period,custom', 'after_or_equal:date_from'],
        ];
    }

    /**
     * @return array{
     *     period: string,
     *     date_from: ?CarbonImmutable,
     *     date_to: ?CarbonImmutable,
     *     period_label: string
     * }
     */
    public function reportFilters(): array
    {
        return app(ResolveDashboardFilters::class)->handle(
            (string) ($this->validated('period') ?: 'all'),
            $this->validated('date_from'),
            $this->validated('date_to'),
        );
    }
}








