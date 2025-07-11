<?php

namespace App\Listeners;

use App\Events\ReportImportEvent;
use App\AmazonAds\Services\ReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class ProcessReportImport implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The report service instance.
     *
     * @var ReportService
     */
    protected $reportService;

    /**
     * Create the event listener.
     *
     * @param ReportService $reportService
     * @return void
     */
    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Handle the event.
     *
     * @param ReportImportEvent $event
     * @return void
     */
    public function handle(ReportImportEvent $event)
    {
        try {
            Log::info('Starting report import processing', [
                'file_path' => $event->filePath,
                'company_id' => $event->companyId
            ]);

            // Check if the file still exists (temporary files might be cleaned up)
            if (!File::exists($event->filePath)) {
                Log::error('File no longer exists', [
                    'file_path' => $event->filePath
                ]);
                return;
            }

            $result = $this->reportService->importCampaignReport($event->filePath, $event->companyId);

            Log::info('Report import processing completed', [
                'processed' => $result['processed'],
                'skipped' => $result['skipped']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process report import', [
                'error' => $e->getMessage(),
                'file_path' => $event->filePath,
                'company_id' => $event->companyId,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Re-throw the exception to mark the job as failed
            throw $e;
        }
    }
} 