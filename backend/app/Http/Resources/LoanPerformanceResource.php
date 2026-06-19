<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanPerformanceResource extends JsonResource
{
    protected $user;

    public function __construct($resource, $user = null)
    {
        parent::__construct($resource);
        $this->user = $user;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'summary' => $this->resource['summary'],
            'installments' => $this->resource['installments'],
            'payments' => $this->resource['payments'],
            'late_fee_projection' => $this->resource['late_fee_projection'],
        ];

        if (!empty($this->resource['chart'])) {
            $data['chart'] = $this->resource['chart'];
        }

        return $data;
    }
}
