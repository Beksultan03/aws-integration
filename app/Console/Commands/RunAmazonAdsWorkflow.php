<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use App\Models\Company;
class RunAmazonAdsWorkflow extends Command
{
    protected $signature = 'amazon:workflow 
        {company_id? : Optional company ID}
        {--start-date= : Start date in YYYY-MM-DD format}
        {--end-date= : End date in YYYY-MM-DD format}';
    protected $description = 'Run the complete Amazon Ads workflow: sync, generate reports, and process';

    public function handle(): int
    {
        $companyId = $this->argument('company_id');
        $startDate = $this->option('start-date') ?? now()->subDay()->toDateString();
        $endDate = $this->option('end-date') ?? now()->toDateString();
        $companyIds = $companyId ? [$companyId] : Company::AVAILABLE_COMPANIES;
        try {
            $this->info('Starting Amazon Ads data sync...');
            foreach ($companyIds as $companyId) {
                $syncResult = Artisan::call("amazon-ads:sync {$companyId}");
            }
            
            if ($syncResult !== 0) {
                $this->error('Amazon Ads sync failed. Aborting workflow.');
                return 1;
            }
            
            $this->info('Amazon Ads sync completed successfully.');
            
            $this->info("Generating reports for {$startDate}...");
            
            $entityTypes = ['campaign', 'keyword', 'productAd', 'searchTerm'];
            
            foreach ($entityTypes as $entityType) {
                foreach ($companyIds as $companyId) {
                    $this->info("Generating {$entityType} reports...");
                    Artisan::call("amazon:schedule-reports {$companyId} --start-date={$startDate} --end-date={$endDate} --entity-type={$entityType}");
                }
            }
            
            $this->info('All reports scheduled successfully.');
            
            $this->info('Processing pending reports...');
            Artisan::call('amazon:process-reports');
            
            $this->info('Amazon Ads workflow completed successfully.');
            return 0;
            
        } catch (\Exception $e) {
            Log::error('Amazon Ads workflow failed: ' . $e->getMessage());
            $this->error('Amazon Ads workflow failed: ' . $e->getMessage());
            return 1;
        }
    }
} 