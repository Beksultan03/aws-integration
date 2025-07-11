<?php

namespace App\Imports;

use App\Event\WindowsKeys\ImportedEvent;
use App\Models\WindowsKey;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class WindowsKeysImport implements ToCollection, WithBatchInserts, WithHeadingRow, WithChunkReading
{


    public function __construct(private $user_id) {
        $this->user_id = $user_id;
    }

    public function collection(Collection $rows): void
    {

        $hashedKeys = $rows->map(function ($row) {
            return hash('sha256', $row['key']);
        });

        $existingKeys = WindowsKey::query()
            ->select('hashed_key')
            ->whereIn('hashed_key', $hashedKeys)
            ->pluck('hashed_key')
            ->toArray();

        $dataToInsert = [];
        $createdKeys = [];

        foreach ($rows as $row) {
            $hashedKey = hash('sha256', $row['key']);

            if (in_array($hashedKey, $createdKeys) || in_array($hashedKey, $existingKeys)) {
                continue;
            }

            $encryptedKey = encrypt($row['key']);
            $dataToInsert[] = [
                'key_type' => $row['key_type'],
                'key' => $encryptedKey,
                'hashed_key' => $hashedKey,
                'vendor' => $row['vendor'],
            ];

            $createdKeys[] = $hashedKey;
        }

        if (!empty($dataToInsert)) {
            WindowsKey::query()->insert($dataToInsert);

            $newKeys = WindowsKey::query()
                ->whereIn('hashed_key', $createdKeys)
                ->pluck('id')
                ->toArray();

            event(new ImportedEvent($this->user_id, $newKeys));
        }
    }

    public function chunkSize(): int
    {
        return 1000;
    }


    public function batchSize(): int
    {
        return 1000;
    }

}





