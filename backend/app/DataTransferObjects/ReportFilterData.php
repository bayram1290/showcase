<?php

namespace App\DataTransferObjects;

use App\Http\Requests\ReportFilterRequest;
use Carbon\Carbon;

final class ReportFilterData
{

    /**
     * ReportFilterData constructor for the class.
     *
     * @param Carbon|null $startDate The start date for filtering.
     * @param Carbon|null $endDate The end date for filtering.
     */
    private function __construct(
        // public readonly int $filterType, // TODO: add filter types
        public readonly ?Carbon $startDate,
        public readonly ?Carbon $endDate
    ) {}

    /**
     * Create a new ReportFilterData instance from a ReportFilterRequest.
     *
     * @param ReportFilterRequest $request
     * @return ReportFilterData
     */
    public static function fromRequest(ReportFilterRequest $request): self
    {
        return new self(
            // filterType: $request->validated('filterType'), // TODO: add filter types
            startDate: $request->validated('from_date') ? Carbon::parse($request->validated('from_date')) : null,
            endDate: $request->validated('to_date') ? Carbon::parse($request->validated('to_date')) : null,
        );
    }
}