<?php

namespace App\AmazonAds\Http\Controllers;

use App\AmazonAds\Http\Requests\Export\ExportListingRequest;
use App\Http\API\Controllers\BaseController;
use App\AmazonAds\Services\ExportService;
use Illuminate\Support\Facades\Storage;

class ExportController extends BaseController
{
    public function __construct(
        private readonly ExportService $exportService
    ) {}

    public function exportListing(ExportListingRequest $request)
    {
        try {
            $fileName = $this->exportService->export($request->validated());
            
            if (!Storage::disk('public')->exists($fileName)) {
                return $this->responseConflict('Export file not found');
            }

            return Storage::disk('public')->download(
                $fileName,
                $fileName,
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Content-Disposition' => 'attachment; filename="' . $fileName . '"'
                ]
            );
        } catch (\Exception $e) {
            return $this->responseConflict($e->getMessage());
        }
    }
}
