<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReportImportEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The file path to the CSV file.
     *
     * @var string
     */
    public $filePath;

    /**
     * The company ID.
     *
     * @var int
     */
    public $companyId;

    /**
     * Create a new event instance.
     *
     * @param string $filePath
     * @param int $companyId
     * @return void
     */
    public function __construct(string $filePath, int $companyId)
    {
        // If the file is a temporary upload, copy it to a more permanent location
        if (file_exists($filePath) && is_uploaded_file($filePath)) {
            $extension = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'csv';
            $newFileName = 'reports/' . $companyId . '/' . Str::uuid() . '.' . $extension;
            
            // Ensure the directory exists
            Storage::disk('local')->makeDirectory('reports/' . $companyId);
            
            // Copy the file to storage
            $storagePath = Storage::disk('local')->path($newFileName);
            copy($filePath, $storagePath);
            
            // Update the file path to the storage location
            $this->filePath = $storagePath;
        } else {
            $this->filePath = $filePath;
        }
        
        $this->companyId = $companyId;
    }
} 