<?php

namespace App\Resources;

use App\Http\DTO\Order\OrderConfigDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderConfigResource extends JsonResource
{
    protected OrderConfigDTO $dto;

    /**
     * Constructor to accept the DTO instance.
     *
     * @param OrderConfigDTO $dto
     */
    public function __construct(OrderConfigDTO $dto)
    {
        $this->dto = $dto;
        parent::__construct($dto);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'order_id' => $this->dto->order_id,
            'page_number' => $this->dto->page_number,
            'serial_number' => $this->dto->serial_number,
            'sku' => $this->dto->sku,
            'display_title' => $this->dto->display_title,
            'details' => $this->dto->details,
            'summary' => $this->transformSummary($this->dto->summary),
        ];
    }

    private function transformSummary(array $summary): array
    {
        foreach ($summary as $key => $value) {
            if (str_starts_with($key, 'storage')) {
                $summary[$key] = $this->convertToGB($value);
            }
        }
        if (isset($summary['ram'])) {
            $summary['ram'] = $this->cleanRamValue($summary['ram']);
        }

        return $summary;
    }

    public function cleanRamValue(?string $ram): ?string
    {
        if (is_null($ram)) {
            return null;
        }
        return preg_replace('/(\d+).*/', '$1', $ram);
    }

    private function convertToGB(string $storage): string
    {
        if (preg_match('/(\d+)TB/', $storage, $matches)) {
            return (int) $matches[1] * 1024;
        }

        if (preg_match('/(\d+)GB/', $storage, $matches)) {
            return $matches[1];
        }

        return $storage;
    }
}
