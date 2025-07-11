<?php

namespace App\AmazonAds\Services;

use App\AmazonAds\Models\PpcChangeLog;
use App\AmazonAds\Models\Campaign;
use App\AmazonAds\Models\AdGroup;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class LogService
{
    public function getEntityLogs(string $entityType, int $entityId, int $perPage = 20): LengthAwarePaginator
    {
        return PpcChangeLog::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('changed_at', 'desc')
            ->paginate($perPage);
    }

    public function getEntityWithRelatedLogs(string $entityType, int $entityId, int $perPage = 20): LengthAwarePaginator
    {
        $logs = collect();

        switch ($entityType) {
            case 'campaign':
                $campaign = Campaign::find($entityId);
                if ($campaign) {
                    $logs = $campaign->getLogs();
                }
                break;
            case 'adGroup':
                $adGroup = AdGroup::find($entityId);
                if ($adGroup) {
                    $logs = $adGroup->getLogs();
                }
                break;
        }

        // Convert to paginated collection
        $page = request()->get('page', 1);
        $offset = ($page - 1) * $perPage;
        
        return new LengthAwarePaginator(
            $logs->slice($offset, $perPage),
            $logs->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
} 