<?php

namespace App\Resources;

use App\Http\DTO\Order\OrderConfigDTO;
use App\Http\DTO\Product\ProductDetailsDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductDetailsResource extends JsonResource
{
    protected array $summary;

    /**
     * Constructor to accept the DTO instance.
     *
     * @param ProductDetailsDTO $dto
     */
    public function __construct(array $summary)
    {
        $this->summary = $summary;
        parent::__construct($summary);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->transformSummary($this->summary);
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
