<?php

namespace App\Console\Commands;

use App\AmazonAds\Models\AmazonAdType;
use App\AmazonAds\Models\AmazonMetricName;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Illuminate\Support\Facades\Log;
class PopulateAmazonMetrics extends Command
{
    protected $signature = 'amazon:populate-metrics {--force : Force repopulate metrics}';
    protected $description = 'Populate Amazon metrics table with standard metrics';

    private function getValueType(array $metricData): string
    {
        return match($metricData['type']) {
            'currency', 'percentage', 'ratio', 'decimal' => 'decimal',
            'integer' => 'integer',
            'string' => 'string',
            'date' => 'date',
            default => 'decimal'
        };
    }

    private function getCommonMetrics(): array
    {
        $reflection = new ReflectionClass(AmazonMetricName::class);
        return $reflection->getStaticPropertyValue('commonMetrics');
    }

    public function handle(): void
    {
        $existingCount = AmazonMetricName::count();
        
        if ($existingCount > 0 && !$this->option('force')) {
            if (!$this->confirm("Found {$existingCount} existing metrics. Do you want to update/add metrics?")) {
                $this->info('Operation cancelled.');
                return;
            }
        }

        DB::beginTransaction();
        try {
            $adType = AmazonAdType::firstOrCreate(
                ['code' => 'SPONSORED_PRODUCTS'],
                [
                    'name' => 'Sponsored Products',
                    'description' => 'Amazon Sponsored Products advertising type',
                    'is_active' => true
                ]
            );

            $entityTypes = [
                AmazonMetricName::ENTITY_TYPE_CAMPAIGN,
                AmazonMetricName::ENTITY_TYPE_KEYWORD,
                AmazonMetricName::ENTITY_TYPE_PRODUCT_AD,
                AmazonMetricName::ENTITY_TYPE_TARGETING,
            ];

            $commonMetrics = $this->getCommonMetrics();
            $allMetrics = [];

            // Create unique metrics without duplicating for each entity type
            foreach ($commonMetrics as $name => $metricData) {
                $allMetrics[] = [
                    'name' => $name,
                    'description' => $metricData['description'],
                    'ad_type_id' => $adType->id,
                    'entity_type' => null,
                    'value_type' => $this->getValueType($metricData),
                ];
            }

            // Add entity-specific metrics
            foreach ($entityTypes as $entityType) {
                $metrics = AmazonMetricName::getMetricsForEntityType($entityType);
                foreach ($metrics as $name => $metricData) {
                    // Skip if this metric is already in common metrics
                    if (isset($commonMetrics[$name])) {
                        continue;
                    }
                    
                    $allMetrics[] = [
                        'name' => $name,
                        'description' => $metricData['description'],
                        'ad_type_id' => $adType->id,
                        'entity_type' => $entityType,
                        'value_type' => $this->getValueType($metricData),
                    ];
                }
            }
            Log::info('$allMetrics: ', [$allMetrics]);

            $bar = $this->output->createProgressBar(count($allMetrics));
            $bar->start();
            $this->info('Updating/inserting metrics...');

            // Create a set of valid metric names
            $validMetricNames = collect($allMetrics)->pluck('name')->unique()->toArray();

            // Delete metrics that are not in use and not in valid combinations
            $metricsToDelete = AmazonMetricName::whereNotIn('name', $validMetricNames)
                ->whereDoesntHave('metrics')
                ->get();

            if ($metricsToDelete->count() > 0) {
                $this->info("\nRemoving {$metricsToDelete->count()} unused metrics:");
                foreach ($metricsToDelete as $metric) {
                    $this->line("- {$metric->name}");
                }
                $metricsToDelete->each->delete();
            }

            foreach (array_chunk($allMetrics, 100) as $chunk) {
                AmazonMetricName::upsert(
                    $chunk,
                    ['name', 'entity_type'],
                    ['description', 'ad_type_id', 'value_type']
                );
                $bar->advance(count($chunk));
            }

            $bar->finish();
            $this->newLine(2);

            DB::commit();
            $this->info('Successfully updated metrics table.');

            foreach ($entityTypes as $entityType) {
                $this->info("\nMetrics for {$entityType}:");
                $metrics = AmazonMetricName::where('entity_type', $entityType)->get();
                
                $this->table(
                    ['Metric Name', 'Description', 'Value Type'],
                    $metrics->map(fn($metric) => [
                        $metric->name,
                        $metric->description,
                        $metric->value_type
                    ])->toArray()
                );
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Failed to update metrics: {$e->getMessage()}");
            $this->error("Stack trace: " . $e->getTraceAsString());
        }
    }
} 