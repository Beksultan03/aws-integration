<?php

namespace App\Services\Parts;

use App\Models\Part;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class PartService
{
    const array PART_TYPE_RAM_ID = [4, 5, 17, 18,28, 29];
    const array PART_TYPE_STORAGE_ID = [
        'hdd' => [9, 10, 16],
        'ssd' => [6, 7, 8,22, 30],
    ];
    const array PART_TYPE_CPU_ID = [25];
    const array PART_TYPE_OS_ID = [15];
    const array PART_TYPE_GPU_ID = [24];
    const array PART_TYPE_WINDOWS_ID = [15];


    public function getPartDetailsByСomponents(Collection $componets, int $totalQty): array
    {
        $groupedParts = [
            'storage' => [
                'hdd' => null,
                'ssd' => null,
            ]
        ];
        $componets->each(function ($part) use (&$groupedParts) {
            switch (true) {
                case $this->isRam($part->part_type_id):
                    $groupedParts['ram'][] = $part;
                    break;
                case $this->isStorage($part->part_type_id):
                    if ($this->isHdd($part->part_type_id)) {
                        $groupedParts['storage']['hdd'][] = $part;
                    } elseif ($this->isSsd($part->part_type_id)) {
                        $groupedParts['storage']['ssd'][] = $part;
                    }
                    break;
                case $this->isCpu($part->part_type_id):
                    $groupedParts['cpu'][] = $part;
                    break;
                case $this->isOs($part->part_type_id):
                    $groupedParts['os'][] = $part;
                    break;
                case $this->isGPU($part->part_type_id):
                    $groupedParts['gpu'][] = $part;
                    break;
            }
        });
        if (empty($groupedParts['storage']['hdd'])) {
            $groupedParts['storage']['hdd'] = null;
        }
        if (empty($groupedParts['storage']['ssd'])) {
            $groupedParts['storage']['ssd'] = null;
        }

        $mappedParts = [];
        foreach ($groupedParts as $group => $partList) {
            if ($group === 'ram') {
                $mappedParts[$group] = $this->handleComponentsWithSameSku($partList,$totalQty);
            }
            elseif ($group === 'storage') {
                if (!empty($partList['ssd'])) {
                    $mappedParts['main_storage'] = [
                        'value' => $this->handleComponentsWithSameSku($partList['ssd'], $totalQty),
                        'type' => 'ssd',
                    ];
                }

                if (!empty($partList['hdd'])) {
                    if (!isset($mappedParts['main_storage'])) {
                        $mappedParts['main_storage'] = [
                            'value' => $this->handleComponentsWithSameSku($partList['hdd'], $totalQty),
                            'type' => 'hdd',
                        ];
                    } else {
                        $mappedParts['additional_storage'] = [
                            'value' => $this->handleComponentsWithSameSku($partList['hdd'], $totalQty),
                            'type' => 'hdd',
                        ];
                    }
                }
            }
            else {
                $partNames = array_map(function($part) {
                    return $part->name;
                }, $partList);
                $mappedParts[$group] = implode(', ', $partNames);
            }
        }

        return $mappedParts;
    }

    private function handleComponentsWithSameSku(array $parts, int $totalQty): string
    {
        $groupedBySku = [];

        foreach ($parts as $part) {
            $sku = $part->sku;
            if (!isset($groupedBySku[$sku])) {
                $groupedBySku[$sku] = [];
            }
            $groupedBySku[$sku][] = $part;
        }

        $result = [];

        foreach ($groupedBySku as $sku => $skuParts) {
            if (count($skuParts) > 1) {
                $result[] = $this->sumComponents($skuParts, $totalQty);
            } else {
                $result[] = $this->convertToGBWithoutPrefix($skuParts[0], $totalQty);
            }
        }

        return implode(', ', $result);
    }

    private function sumComponents(array $parts, int $totalQty): string
    {
        $totalSize = 0;
        foreach ($parts as $part) {
            preg_match('/(\d+)(GB|TB) (.+)/', $part->name, $matches);
            if ($matches) {
                $size = (int) $matches[1];
                $unit = $matches[2];
                $resultSize = $this->convertToGB($size, $unit);
                $qty = $part->qty/$totalQty;
                $totalSize += $qty * $resultSize;
            }
        }

        return $totalSize;
    }
    public function getBySkuList(array $allSku): Collection
    {
        return Part::query()
            ->select('name', 'part_type_id', 'sku')
            ->whereIn('sku', $allSku)
            ->get();
    }

    private function convertToGBWithoutPrefix(Model $part, int $totalQty): string
    {
        preg_match('/(\d+)(GB|TB) (.+)/', $part->name, $matches);

        if ($matches) {
            $size = (int) $matches[1];
            $unit = $matches[2];

            $resultSize = $this->convertToGB($size, $unit);
            $qty = $part->qty / $totalQty;
            return $qty * $resultSize;
        }

        return $part->name;
    }

    private function convertToGB(int $size, string $unit): int
    {
        return $unit === 'TB' ? $size * 1024 : $size;
    }

    private function isRam($partTypeId): bool
    {
        return in_array($partTypeId, PartService::PART_TYPE_RAM_ID);
    }

    private function isStorage($partTypeId): bool
    {
        return in_array($partTypeId, array_merge(PartService::PART_TYPE_STORAGE_ID['hdd'], PartService::PART_TYPE_STORAGE_ID['ssd']));
    }

    private function isHdd($partTypeId): bool
    {
        return in_array($partTypeId, PartService::PART_TYPE_STORAGE_ID['hdd']);
    }

    private function isSsd($partTypeId): bool
    {
        return in_array($partTypeId, PartService::PART_TYPE_STORAGE_ID['ssd']);
    }

    private function isCpu($partTypeId): bool
    {
        return in_array($partTypeId, PartService::PART_TYPE_CPU_ID);
    }

    private function isOs($partTypeId): bool
    {
        return in_array($partTypeId, PartService::PART_TYPE_OS_ID);
    }

    private function isGPU($partTypeId): bool
    {
        return in_array($partTypeId, PartService::PART_TYPE_GPU_ID);
    }

    public function checkPartType($sku): array
    {
        $result = [];

        $checkIfOS = Part::query()
            ->select('name')
            ->where('sku', $sku)
            ->whereIn('part_type_id', PartService::PART_TYPE_WINDOWS_ID)
            ->first();

        if ($checkIfOS) {
            $result['os'] = $checkIfOS->name;
        }

        return $result;
    }
}
