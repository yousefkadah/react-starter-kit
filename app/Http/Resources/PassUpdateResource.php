<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\PassUpdate
 */
class PassUpdateResource extends JsonResource
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
            'fields_changed' => $this->fields_changed,
            'apple_delivery_status' => $this->apple_delivery_status,
            'google_delivery_status' => $this->google_delivery_status,
            'apple_devices_notified' => $this->apple_devices_notified,
            'google_updated' => (bool) $this->google_updated,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at,
        ];
    }
}
