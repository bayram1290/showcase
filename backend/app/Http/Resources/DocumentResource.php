<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\User;
use Carbon\Carbon;

class DocumentResource extends JsonResource
{
    /**
     * Convert the object data into an array representation by extracting relevant data.
     *
     * @param Request $request The request object.
     * @return array<string, mixed> The array representation of the object.
     */
    public function toArray(Request $request): array
    {

        $data = [
            'uuid' => $this->uuid,
            'document_type' => $this->document_type,
            'original_name' => $this->original_name,
            'file_size' => $this->file_size_human,
            'mime_type' => $this->mime_type,
            'is_verified' => $this->is_verified,
            'verified_at' => $this->verified_at?->toDateString(),
            'verification_notes' => $this->verification_notes,
            'created_at' => $this->created_at->toDateString(),
            'file_url' => $this->file_url,
            'thumbnail_url' => $this->thumbnail_url,
        ];

        if ($request->user() instanceof User) {
            $data['scan_status'] = $this->scan_status;
            $data['last_scanned_at'] = $this->last_scanned_at ? Carbon::parse($this->last_scanned_at)->format('F j, Y') : null;
        }

        return $data;
    }
}
