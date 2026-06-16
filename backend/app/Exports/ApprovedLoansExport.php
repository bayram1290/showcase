<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;

use App\Models\LoanApplication;;
use App\Models\BankBranch;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ApprovedLoansExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $loans;
    private const LOAN_HIGH_AMOUNT = 50000;


    public function __construct($loans)
    {
        $this->loans = $loans;
    }

    /**
     * Returns the collection of loan data to be exported.
     *
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->loans;
    }

    /**
     * Returns an array of column headings for the ApprovedLoansExport class.
     *
     * @return array The array of column headings.
     */
    public function headings(): array
    {
        return [
            'Reference Number',
            'Borrower Name',
            'Phone Number',
            'Email',
            'Loan Product',
            'Amount',
            'Approved Date',
            'Disbursed Since Approval',
            'Disbursement Status',
            'Assigned Officer',
            'Bank Branch',
            'High Value Flag',
        ];
    }

    /**
     * Maps the loan data to an array of values for export.
     *
     * @param LoanApplication $loan The loan data to be mapped.
     * @return array The mapped loan data.
     */
    public function map($loan): array
    {
        return [
            $loan->application_ref,
            $loan->borrower->getFullName(),
            $loan->borrower->phone,
            $loan->borrower->email,
            $loan->loanProduct->name,
            $loan->amount,
            $loan->approved_at ? Carbon::parse($loan->approved_at)->format('F j, Y') : 'Not Approved',
            $loan->disbursed_at ? Carbon::parse($loan->disbursed_at)->diffForHumans() : 'Not Disbursed',
            $loan->status,
            $loan->assignedOfficer?->first_name . ' ' . $loan->assignedOfficer?->last_name,
            $loan->bankBranch ? BankBranch::find($loan->bank_branch)->name : 'Not Assigned',
            $loan->amount >= self::LOAN_HIGH_AMOUNT ? 'Yes' : 'No',
        ];
    }

    /**
     * Applies styles to the worksheet.
     *
     * @param Worksheet $sheet The worksheet to apply styles to.
     * @return array The array of styles to apply.
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true // Make the first row bold (Header row)
            ]]
        ];
    }
}
