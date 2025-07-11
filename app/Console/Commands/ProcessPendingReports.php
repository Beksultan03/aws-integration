<?php

namespace App\Console\Commands;

use App\AmazonAds\Jobs\ProcessAdvertisingReport;
use App\AmazonAds\Models\AmazonReport;
use App\AmazonAds\Models\AmazonMetricName;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessPendingReports extends Command
{
    protected $signature = 'amazon:process-reports';
    protected $description = 'Process pending Amazon advertising reports';

    public function handle(): void
    {
        $this->info('Starting to process pending reports...');

        $metricsCount = AmazonMetricName::count();

        if ($metricsCount === 0) {
            $this->warn('No metrics found in the database. Please populate metrics first.');
            return;
        }

        $pendingReports = AmazonReport::query()
            ->where('status', 'PENDING')
            ->orWhere('status', 'FAILED')
            ->where(function ($query) {
                $query->where('attempts', '<', 100)
                    ->orWhereNull('last_attempt_at')
                    ->orWhere('last_attempt_at', '<=', now()->subHours(1));
            })
            ->get();

        $this->info("Found {$pendingReports->count()} pending reports");
        Log::info("Found pending reports", [
            'count' => $pendingReports->count(),
            'reportIds' => $pendingReports->pluck('report_id')->toArray()
        ]);

        foreach ($pendingReports as $report) {
            try {
                $this->info("Processing report: {$report->report_id}");
                Log::info('Dispatching report processing', [
                    'reportId' => $report->report_id,
                    'reportType' => $report->report_type,
                    'companyId' => $report->company_id,
                    'attempt' => $report->attempts + 1,
                    'startDate' => $report->start_date,
                    'endDate' => $report->end_date
                ]);

                ProcessAdvertisingReport::dispatch(
                    $report->company_id,
                    $report->report_id,
                    $report->report_type,
                    $report->id
                );

                $report->update([
                    'attempts' => $report->attempts + 1,
                    'last_attempt_at' => now()
                ]);

                $this->info("Dispatched report processing for {$report->report_id}");
                Log::info('Successfully dispatched report processing', [
                    'reportId' => $report->report_id,
                    'status' => 'dispatched'
                ]);

            } catch (\Exception $e) {
                $this->error("Failed to dispatch report processing for {$report->report_id}: {$e->getMessage()}");
                Log::error('Failed to dispatch report processing', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'reportId' => $report->report_id,
                    'reportType' => $report->report_type,
                    'companyId' => $report->company_id
                ]);

                // Update report status to failed if max attempts reached
                if ($report->attempts >= 99) {
                    $report->update([
                        'status' => 'FAILED',
                        'last_attempt_at' => now()
                    ]);
                    Log::warning('Report marked as failed due to max attempts', [
                        'reportId' => $report->report_id,
                        'attempts' => $report->attempts
                    ]);
                }
            }
        }

        $this->info('Finished processing pending reports');
        Log::info('Finished processing pending reports command');
    }
} 