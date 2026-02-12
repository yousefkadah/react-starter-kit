<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PassDistributionLinkResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pass_id' => $this->pass_id,
            'slug' => $this->slug,
            'status' => $this->status,
            'url' => $this->url(),
            'last_accessed_at' => $this->last_accessed_at?->toIso8601String(),
            'accessed_count' => $this->accessed_count,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
