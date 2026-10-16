<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'user' => $this->user?->name,
            'username' => $this->user?->username,
            'subject' => $this->auditable_type === null ? null : class_basename($this->auditable_type).' #'.$this->auditable_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip' => $this->ip,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
