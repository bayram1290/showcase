<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Maatwebsite\Excel\Facades\Excel;

use App\Contracts\Services\ReportServiceInterface;
use App\Http\Requests\ReportFilterRequest;
use App\DataTransferObjects\ReportFilterData;
use App\Http\Resources\DashboardReportResource;

use App\Exports\ApprovedLoansExport;
use App\Http\Resources\ApprovedLoansReportResource;
use App\Http\Resources\NpaLoanReportResource;

use Carbon\Carbon;

class DashboardReportController extends Controller
{
    use AuthorizesRequests;
    protected ReportServiceInterface $service;

    /**
     * Constructor for the class.
     *
     * @param ReportServiceInterface $service The service used for generating reports.
     */
    public function __construct(
        ReportServiceInterface $service
    ) {
        $this->service = $service;
    }

    /**
     * GEt the dashboard report based on the provided filter request.
     *
     * @param ReportFilterRequest $request The filter request containing the report parameters.
     * @return Response The API response containing the dashboard report data.
     * @throws \Exception If an error occurs while retrieving the dashboard report.
     */
    public function dashboard(ReportFilterRequest $request): Response
    {

        try {
            $user = $request->user() ?? request()->user();
            $this->authorize('view-report-dashboard', $user);

            $filter_data = ReportFilterData::fromRequest($request);
            $metrics = $this->service->getDashboardMetrics($filter_data, $user);

            return ApiResponse::success(
                new DashboardReportResource($metrics),
                'DASHBOARD_REPORT_SUCCESS',
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'DASHBOARD_REPORT_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * Get the approved loans report based on the provided filter request.
     *
     * @param ReportFilterRequest $request The filter request containing the report parameters.
     * @return Response The API response containing the approved loans report data.
     * @throws \Exception If an error occurs while retrieving the approved loans report.
     */
    public function approvedLoans(ReportFilterRequest $request): Response
    {
        try {
            $user = $request->user() ?? request()->user();
            $this->authorize('view-report-approved-loans', $user);

            $filter_data = ReportFilterData::fromRequest($request);
            $loans = $this->service->getApprovedLoans($filter_data, $user);

            return ApiResponse::success(
                ApprovedLoansReportResource::collection($loans),
                'APPROVED_LOANS_SUCCESS',
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'APPROVED_LOANS_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * Get the NPA loans report based on the provided filter request.
     *
     * @param ReportFilterRequest $request The filter request containing the report parameters.
     * @return Response The API response containing the NPA loans report data.
     * @throws \Exception If an error occurs while retrieving the NPA loans report.
     */
    public function npaLoans(ReportFilterRequest $request): Response
    {
        try {
            $user = $request->user() ?? request()->user();
            $this->authorize('view-report-npa', $user);

            $filter_data = ReportFilterData::fromRequest($request);
            $loans = $this->service->getNpaLoans($filter_data, $user);

            return ApiResponse::success(
                NpaLoanReportResource::collection($loans),
                'NPA_LOANS_SUCCESS',
                Response::HTTP_OK
            );

        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'NPA_LOANS_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * Export the approved reports based on the provided filter request.
     *
     * @param ReportFilterRequest $request The filter request containing the report parameters.
     * @return Response The response containing the exported approved reports.
     * @throws \Exception If an error occurs while exporting the approved reports.
     */
    public function exportApprovedLoans(ReportFilterRequest $request): Excel|Response
    {
        try {
            $user = $request->user() ?? request()->user();
            $this->authorize('export-approved-reports', $user);

            $filter_data = ReportFilterData::fromRequest($request);
            $loans = $this->service->exportApprovedLoans($filter_data, $user);

            $file_prefix = '';

            if ($request->has('from_date')) {
                $file_prefix = Carbon::parse($request->from_date)->format('Y-m-d');
            }

            if ($request->has('to_date')) {
                $file_prefix .= '_' . Carbon::parse($request->to_date)->format('Y-m-d');
            }

            if ($file_prefix === '') {
                $file_prefix = Carbon::now()->format('Y-m-d');
            }

            return Excel::download(
                new ApprovedLoansExport($loans),
                $file_prefix . '_approved_loans.xlsx'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'EXPORT_APPROVED_REPORTS_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }
}
