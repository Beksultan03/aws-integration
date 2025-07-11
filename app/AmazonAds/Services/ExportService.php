<?php

namespace App\AmazonAds\Services;

use Maatwebsite\Excel\Facades\Excel;
use App\AmazonAds\Exports\AmazonAdsExport;
use App\AmazonAds\Models\Campaign;
use App\AmazonAds\Models\AdGroup;
use App\AmazonAds\Models\Keyword;
use App\AmazonAds\Models\NegativeKeyword;
use App\AmazonAds\Models\ProductAd;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ExportService
{
    public function __construct(
        private readonly CampaignService $campaignService,
        private readonly AdGroupService $adGroupService,
        private readonly KeywordService $keywordService,
        private readonly NegativeKeywordService $negativeKeywordService,
        private readonly ProductAdService $productAdService
    ) {}

    public function export(array $data): string
    {
        $type = $data['type'];
        $filters = json_decode($data['filters'], true);
        $dateFrom = null;
        $dateTo = null;
        if(isset($filters['dateFrom'])) {
            $dateFrom = Carbon::parse($filters['dateFrom']);
        }
        if(isset($filters['dateTo'])) {
            $dateTo = Carbon::parse($filters['dateTo']);
        }

        $parentId = $data['parentId'] ?? null;
        
        $headers = $this->getHeadersForType($type);
        $records = $this->getDataForType($type, $dateFrom, $dateTo, $parentId);
        
        $fileName = sprintf(
            '%s_export_%s_%s.xlsx',
            $type,
            $dateFrom ? $dateFrom->format('Y-m-d') : '',
            $dateTo ? $dateTo->format('Y-m-d') : ''
        );

        Excel::store(
            new AmazonAdsExport($headers, $records),
            $fileName,
            'public'
        );

        return $fileName;
    }

    private function getHeadersForType(string $type): array
    {
        return match($type) {
            'campaign' => [
                'Name', 'State', 'Start Date', 
                'End Date', 'Budget Amount', 'Budget Type',
                'Targeting Type'
            ],
            'ad-group' => [
                'Name', 'State', 'Default Bid',
            ],
            'keywords' => [
                'Match Type', 'State', 'Bid',
             'Text'
            ],
            'negativeKeywords' => [
                'Match Type', 'State', 'Text',
            ],
            'products' => [
                'ASIN', 'SKU', 'State'
            ],
            default => throw new \InvalidArgumentException('Invalid export type'),
        };
    }

    private function getDataForType(string $type, $dateFrom, $dateTo, ?int $parentId = null): array
    {
        $query = match($type) {
            'campaign' => Campaign::query()
                ->when($dateFrom, function ($query) use ($dateFrom) {   
                    return $query->where('created_at', '>=', $dateFrom);
                })
                ->when($dateTo, function ($query) use ($dateTo) {
                    return $query->where('created_at', '<=', $dateTo);
                })
                ->get()
                ->map(fn($item) => [
                    $item->name,
                    $item->state,
                    $item->start_date,
                    $item->end_date,
                    $item->budget_amount,
                    $item->budget_type,
                    $item->targeting_type,
                ]),
            'ad-group' => AdGroup::query()
                ->when($dateFrom, function ($query) use ($dateFrom) {   
                    return $query->where('created_at', '>=', $dateFrom);
                })
                ->when($dateTo, function ($query) use ($dateTo) {
                    return $query->where('created_at', '<=', $dateTo);
                })
                ->when($parentId, function ($query) use ($parentId) {
                    return $query->where('campaign_id', $parentId);
                })
                ->get()
                ->map(fn($item) => [
                    $item->name,
                    $item->state,
                    $item->default_bid,
                ]),
            'keywords' => Keyword::query()
                ->when($dateFrom, function ($query) use ($dateFrom) {   
                    return $query->where('created_at', '>=', $dateFrom);
                })
                ->when($dateTo, function ($query) use ($dateTo) {
                    return $query->where('created_at', '<=', $dateTo);
                })
                ->when($parentId, function ($query) use ($parentId) {
                    return $query->where('ad_group_id', $parentId);
                })
                ->get()
                ->map(fn($item) => [
                    $item->match_type,
                    $item->state,
                    $item->bid,
                    $item->keyword_text,
                ]),
            'negativeKeywords' => NegativeKeyword::query()
                ->when($dateFrom, function ($query) use ($dateFrom) {   
                    return $query->where('created_at', '>=', $dateFrom);
                })
                ->when($dateTo, function ($query) use ($dateTo) {
                    return $query->where('created_at', '<=', $dateTo);
                })
                ->when($parentId, function ($query) use ($parentId) {
                    return $query->where('ad_group_id', $parentId);
                })
                ->get()
                ->map(fn($item) => [
                    $item->match_type,
                    $item->state,
                    $item->keyword_text,
                ]),
            'products' => ProductAd::query()
                ->when($dateFrom, function ($query) use ($dateFrom) {   
                    return $query->where('created_at', '>=', $dateFrom);
                })
                ->when($dateTo, function ($query) use ($dateTo) {
                    return $query->where('created_at', '<=', $dateTo);
                })
                ->when($parentId, function ($query) use ($parentId) {
                    return $query->where('ad_group_id', $parentId);
                })
                ->get()
                ->map(fn($item) => [
                    $item->asin,
                    $item->sku,
                    $item->state,
                ]),
            default => throw new \InvalidArgumentException('Invalid export type'),
        };

        return $query->toArray();
    }
}

