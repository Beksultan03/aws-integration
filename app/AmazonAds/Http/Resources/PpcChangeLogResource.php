<?php

namespace App\AmazonAds\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PpcChangeLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entityType' => $this->entity_type,
            'entityId' => $this->entity_id,
            'fieldName' => $this->field_name,
            'oldValue' => $this->old_value,
            'newValue' => $this->new_value,
            'action' => $this->action,
            'changedAt' => $this->changed_at,
            'user_name' => $this->user?->fname . ' ' . $this->user?->lname,
        ];
    }
} 