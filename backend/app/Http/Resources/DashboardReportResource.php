<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardReportResource extends JsonResource
{

    /**
     * Convert the object data to an array representation.
     *
     * Reconstruct the object data into an array with the following keys:
     * - summary => an array containing various summary statistics, including:
     *    total_disbursed,
     *    total_outstanding,
     *    total_repaid,
     *    disbursement_rate,
     *    repayment_rate,
     *    loans_count,
     *    collection_rate,
     *    npa_rate,
     *    health_score,
     *    health_status
     * - disbursement => an array containing disbursement statistics, including:
     *    total,
     *    rate,
     *    repayment_rate,
     *    accounts,
     * - collection => an array containing collection statistics, including:
     *     total,
     *     late_fees,
     *     rate
     * - npa => an array containing NPA statistics, including:
     *     count,
     *     rate,
     *     active_loans,
     *     risk_level
     * - loan_status => an array containing loan status statistics, including:
     *    approved,
     *    closed,
     *    disbursed,
     *    rejected,
     *    under_review,
     *    pending_review
     * - alerts => an array containing the generated alerts
     *
     * If an exception occurs during the conversion process, an array with an 'error' key and the exception message is returned.
     *
     * @param Request $request The request object.
     * @return array<string, mixed> The array representation of the object data.
     */
    public function toArray(Request $request): array
    {
        try {

            $disbursement = $this['disbursement'];
            $loan_status = $this['loan_status'];
            $collection = $this['collection'];
            $npa = $this['npa'];

            $collection_rate = $disbursement['total_disbursed'] > 0
                ? ($collection['total_collected'] / $disbursement['total_disbursed']) * 100
                : 0;

            $npa_percentage = $npa['npa_percentage'];

            $alerts = $this->generateAlerts($collection_rate, $npa_percentage, $loan_status['under_review']);
// dd($npa);
            return [
                'summary' => [
                    'total_disbursed' => number_format($disbursement['total_disbursed'], 2),
                    'total_outstanding' => number_format($disbursement['total_outstanding'], 2),
                    'total_repaid' => number_format($disbursement['total_repaid'], 2),
                    'disbursement_rate' => number_format($disbursement['disbursement_rate'], 2),
                    'repayment_rate' => number_format($disbursement['repayment_rate'], 2),
                    'loans_count' => $disbursement['account_count'],
                    'collection_rate' => number_format($collection_rate, 1) . '%',
                    'npa_rate' => number_format($npa_percentage, 1) . '%',
                    'health_score' => $this['health_score'],
                    'health_status' => $this->getHealthStatus($this['health_score']),
                ],
                'disbursement' => [
                    'approved' => $disbursement['total_approved'],
                    'disbursed' => $disbursement['total_disbursed'],
                    'outstanding' => $disbursement['total_outstanding'],
                    'repaid' => $disbursement['total_repaid'],
                    'accounts' => $disbursement['account_count'],
                    'disbursement_rate' => number_format($disbursement['disbursement_rate'], 2),
                    'repayment_rate' => number_format($disbursement['repayment_rate'], 2),
                ],
                'collection' => [
                    'total' => number_format($collection['total_collected'], 2),
                    'late_fees' => number_format($collection['total_late_fee'], 2),
                    'rate' => number_format($collection_rate, 1) . '%',
                ],
                'npa' => [
                    'count' => $npa['npa_count'],
                    'rate' => number_format($npa_percentage, 1) . '%',
                    'active_loans' => $npa['active_accounts_count'],
                    'amount' => number_format($npa['npa_amount'], 2),
                    'outstanding' => number_format($npa['total_outstanding'], 2),
                    'risk_level' => $this->getRiskLevel($npa_percentage),
                ],
                'loan_status' => [
                    'approved' => $loan_status['approved'],
                    'closed' => $loan_status['closed'],
                    'disbursed' => $loan_status['disbursed'],
                    'rejected' => $loan_status['rejected'],
                    'under_review' => $loan_status['under_review'],
                    'pending_review' => $loan_status['under_review'] > 10 ? 'High' : ($loan_status['under_review'] > 5 ? 'Medium' : 'Low'),
                ],
                'alerts' => $alerts
            ];

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate alerts based on the collection rate (CR), NPA percentage (NPA_p), and under review count (URC).
     *
     * - CR < 50 => warning - CR < 70 => info
     * - NPA_p > 10 => critical - NPA_p > 5 => warning
     * - URC > 20 => critical - URC > 10 => warning
     *
     * @param float $collectionRate
     * @param float $npaPercentage
     * @param int $underReview
     * @return array<int, array{type:string,message:string}>
     */
    private function generateAlerts(float $collectionRate, float $npaPercentage, int $underReview): array
    {
        $alerts = [];

        $push_alert_fn = function (string $type, string $message) use (&$alerts) {
            $alerts[] = [
                'type' => $type,
                'message' => $message,
            ];
        };

        // Collection rate checks
        if ($collectionRate < 50.0) {
            $push_alert_fn('warning', 'Collection rate is below 50%. Consider tightening follow‑up.');
        } else if ($collectionRate < 70.0) {
            $push_alert_fn('info', 'Collection rate is below target (70%). Monitor closely.');
        }

        // NPA percentage checks
        if ($npaPercentage > 10.0) {
            $push_alert_fn('critical', 'NPA percentage is above 10%. Immediate action required.');
        } else if ($npaPercentage > 5.0) {
            $push_alert_fn('warning', 'NPA percentage is above target (5%). Review overdue accounts.');
        }

        // Under review checks
        if ($underReview > 20) {
            $push_alert_fn('critical', "There are a lot of ({$underReview}) application stuck in ***under review*** status. Assign officers to review these accounts, IMMEDIATELY");
        } else if ($underReview > 15) {
            $push_alert_fn('warning', "There are ({$underReview}) applications stuck in ***under review*** status. Please, assign officers to review these accounts.");
        }

        return $alerts;
    }

    /**
     * Return the health status string based on the provided health status score.
     *
     * @param int $score The health status score.
     * @return string The health status string.
    */
    private function getHealthStatus(int $score): string
    {
        return match (true) {
            ($score >= 80) => 'Excellent',
            ($score >= 60) => 'Good',
            ($score >= 40) => 'Fair',
            ($score >= 20) => 'Poor',
            default => 'Critical',
        };
    }

    /**
     * Return the risk level string based on the provided NPA percentage.
     *
     * @param float $npaPercentage The NPA percentage.
     * @return string The risk level string.
     */
    private function getRiskLevel(float $npaPercentage): string
    {
        return match (true) {
            ($npaPercentage >= 10) => 'High',
            ($npaPercentage >= 5) => 'Medium',
            default => 'Low',
        };
    }
}
