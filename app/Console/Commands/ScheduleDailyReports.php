<?php

namespace App\Console\Commands;

use App\AmazonAds\Jobs\GenerateAdvertisingReports;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScheduleDailyReports extends Command
{
    protected $signature = 'amazon:schedule-reports 
        {company_id? : Optional company ID to generate reports for}
        {--start-date= : Start date in Y-m-d format}
        {--end-date= : End date in Y-m-d format}
        {--entity-type= : Entity type (campaign, keyword, productAd, searchTerm, productTargeting)}';

    protected $description = 'Schedule daily Amazon advertising reports for all or specific company';

    public function handle(): void
    {
        $companyId = $this->argument('company_id');
        $startDate = $this->option('start-date') ?? now()->subDay()->toDateString();
        $endDate = $this->option('end-date') ?? now()->subDay()->toDateString();
        $entityType = $this->option('entity-type');

        if ($this->option('start-date') || $this->option('end-date')) {
            if (!$this->validateDates($startDate, $endDate)) {
                return;
            }
        }

        if ($entityType && !$this->validateEntityType($entityType)) {
            return;
        }

        $query = Company::whereIn('company_id', Company::AVAILABLE_COMPANIES);
        
        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $companies = $query->get();

        if ($companies->isEmpty()) {
            $this->error('No companies found' . ($companyId ? " with ID: {$companyId}" : ''));
            return;
        }

        foreach ($companies as $company) {
            try {
                GenerateAdvertisingReports::dispatch(
                    $company->company_id,
                    $startDate,
                    $endDate,
                    $entityType
                );

                Log::info('Scheduled report generation', [
                    'company_id' => $company->company_id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'entity_type' => $entityType ?? 'all'
                ]);

                $this->info("Scheduled reports for company: {$company->company_id} from {$startDate} to {$endDate}" . 
                    ($entityType ? " for {$entityType}" : ''));

            } catch (\Exception $e) {
                Log::error('Failed to schedule report', [
                    'error' => $e->getMessage(),
                    'company_id' => $company->company_id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'entity_type' => $entityType
                ]);
                
                $this->error("Failed to schedule reports for company {$company->company_id}: {$e->getMessage()}");
            }
        }
    }

    private function validateDates(string $startDate, string $endDate): bool
    {
        try {
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);

            if ($start > $end) {
                $this->error('Start date cannot be after end date');
                return false;
            }

            if ($end > now()) {
                $this->error('End date cannot be in the future');
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->error('Invalid date format. Please use Y-m-d format (e.g. 2025-02-19)');
            return false;
        }
    }

    private function validateEntityType(string $entityType): bool
    {
        $validTypes = ['campaign', 'keyword', 'productAd', 'searchTerm', 'productTargeting'];
        
        if (!in_array($entityType, $validTypes)) {
            $this->error("Invalid entity type. Valid types are: " . implode(', ', $validTypes));
            return false;
        }

        return true;
    }
} 