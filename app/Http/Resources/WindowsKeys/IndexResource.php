<?php

namespace App\Http\Resources\WindowsKeys;

use App\Http\Resources\Logs\ListResource;
use App\Models\WindowsKey;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $order_id
 * @property string $serial_key
 * @property string $key
 * @property string status
 * @property string vendor
 * @property string key_type
 * @property mixed created_at
 * @property mixed $updated_at
 */
class IndexResource extends JsonResource
{
    private $maskedKey = 'XXXXX-XXXXX-XXXXX-XXXXX';
    public function toArray($request): array
    {

        return [
            'id' => $this->id,
            'vendor' => $this->vendor,
            'key' => $this->getDecryptedKey(),
            'key_type' => $this->key_type,
            'status' => $this->status,
            'order_id' => $this->order_id,
            'serial_key' => $this->serial_key,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'logs' => ListResource::collection($this->logs),
        ];
    }

    private function getDecryptedKey(): string
    {
        return in_array($this->status, [WindowsKey::KEY_TYPE_NOT_USED, WindowsKey::KEY_TYPE_DOWNLOADED, WindowsKey::KEY_TYPE_TRANSFERRED]) ? $this->maskedKey : decrypt($this->key);
    }
}
