<?php

namespace App\AmazonAds\Services\Amazon;

use App\AmazonAds\Exceptions\AmazonAdsException;
use App\AmazonAds\Models\Keyword;
use App\AmazonAds\Models\AdGroup;
use App\AmazonAds\Services\AdsApiClient;
use App\Models\Company;
use Illuminate\Support\Facades\Log;
use App\AmazonAds\Helpers\DateFormatter;
use App\AmazonAds\Enums\AmazonAction;
use App\AmazonAds\Enums\EventLogStatus;
use App\AmazonAds\Models\AmazonEventDispatchLog;
use App\AmazonAds\Models\AmazonEventResponseLog;
use App\AmazonAds\Traits\AmazonApiTrait;
use App\AmazonAds\Http\DTO\Keyword\CreateDTO;
class ApiKeywordService
{
    use AmazonApiTrait;

    public function __construct(
        private readonly AdsApiClient $adsApiClient
    ) {}

    /**
     * Sync all keywords from Amazon Advertising API
     */
    public function syncKeywords(int $companyId): array
    {
        try {
            $allKeywords = [];
            $nextToken = null;

            $company = Company::where('company_id', $companyId)->first();
            if (!$company) {
                throw new AmazonAdsException("Company not found");
            }

            // Get all ad groups for this company to map IDs
            $adGroupIdMap = AdGroup::whereHas('campaign', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->get(['amazon_ad_group_id', 'id', 'campaign_id'])->keyBy('amazon_ad_group_id')->toArray();

            do {
                $payload = [
                    'includeExtendedDataFields' => true,
                    'maxResults' => 100,
                ];

                if ($nextToken) {
                    $payload['nextToken'] = $nextToken;
                }

                $response = $this->adsApiClient->sendRequest(
                    '/sp/keywords/list',
                    $payload,
                    'POST',
                    'application/vnd.spKeyword.v3+json',
                    $companyId
                );

                if (!empty($response['keywords'])) {
                    $this->processKeywordBatch($response['keywords'], $companyId, $adGroupIdMap);
                    $allKeywords = array_merge($allKeywords, $response['keywords']);
                }

                $nextToken = $response['nextToken'] ?? null;

            } while ($nextToken);

            return [
                'success' => true,
                'message' => 'Keywords synced successfully',
                'count' => count($allKeywords)
            ];

        } catch (AmazonAdsException $e) {
            Log::error('Failed to sync keywords: ' . $e->getMessage());
            throw new AmazonAdsException("Failed to sync keywords: " . $e->getMessage());
        }
    }

    /**
     * Process a batch of keywords from Amazon API
     */
    private function processKeywordBatch(array $keywords, int $companyId, array $adGroupIdMap): void
    {
        $toUpsert = [];

        foreach ($keywords as $keywordData) {
            // Skip if we don't have the ad group mapped
            if (!isset($adGroupIdMap[$keywordData['adGroupId']])) {
                Log::warning('Ad group not found for keyword', [
                    'amazon_ad_group_id' => $keywordData['adGroupId'],
                    'keyword_id' => $keywordData['keywordId']
                ]);
                continue;
            }

            $data = [
                'amazon_keyword_id' => $keywordData['keywordId'],
                'campaign_id' => $adGroupIdMap[$keywordData['adGroupId']]['campaign_id'],
                'ad_group_id' => $adGroupIdMap[$keywordData['adGroupId']]['id'],
                'keyword_text' => $keywordData['keywordText'],
                'match_type' => $keywordData['matchType'],
                'state' => $keywordData['state'],
                'bid' => $keywordData['bid'] ?? null,
                'updated_at' => DateFormatter::formatDateTime($keywordData['extendedData']['lastUpdateDateTime'] ?? null),
                'created_at' => DateFormatter::formatDateTime($keywordData['extendedData']['creationDateTime'] ?? null),
            ];

            $toUpsert[] = $data;
        }

        if (!empty($toUpsert)) {
            Keyword::upsert(
                $toUpsert,
                ['amazon_keyword_id', 'ad_group_id'],
                [
                    'keyword_text',
                    'match_type',
                    'state',
                    'bid',
                    'created_at',
                    'updated_at',
                ]
            );
        }
    }

    public function create(array $data): array
    {
        $eventLog = AmazonEventDispatchLog::create([
            'event_type' => AmazonAction::CREATE_KEYWORD->value,
            'payload' => $data,
            'status' => EventLogStatus::PROCESSING->value,
        ]);

        try {

            $response = $this->adsApiClient->sendRequest('/sp/keywords', [
                'keywords' => [$data['data']->toArray()]
            ], 'POST', 'application/vnd.spKeyword.v3+json');

            $errorMessage = null;

            if (!empty($response['keywords']['error'])) {
                $error = $response['keywords']['error'][0]['errors'][0] ?? null;
                if ($error) {
                    $errorMessage = $error['errorType'] ?? 'failedToCreate';
                }
            }

            if (!empty($response['keywords']['success'])) {
                Keyword::query()
                    ->where('id', '=', $data['local_id'])
                    ->update(['amazon_keyword_id' => $response['keywords']['success'][0]['keywordId']]);
            }

            $responseStatus = $errorMessage ? 422 : 200;

            AmazonEventResponseLog::create([
                'event_dispatch_id' => $eventLog->id,
                'http_status' => $responseStatus,
                'response_data' => $response,
                'error_message' => $errorMessage,
                'entity_id' => $data['local_id'],
                'entity_type' => 'keyword',
            ]);

            return $response;

        } catch (AmazonAdsException $e) {
            AmazonEventResponseLog::create([
                'event_dispatch_id' => $eventLog->id,
                'http_status' => 500,
                'response_data' => [],
                'error_message' => "Failed to create keyword: " . $e->getMessage(),
                'entity_id' => $data['local_id'],
                'entity_type' => 'keyword',
                ]);
            
            throw new AmazonAdsException("Failed to create keyword: " . $e->getMessage());
        }
    }

    public function createBatch($keywords, string $amazonCampaignId, string $amazonAdGroupId): array
    {
        try {            
            $keywordDTOs = $keywords->map(function ($keyword) use ($amazonCampaignId, $amazonAdGroupId) {
                $keywordDTO = new CreateDTO(
                    $amazonCampaignId,
                    $keyword->match_type,
                    $keyword->state,
                    (float)$keyword->bid,
                    $amazonAdGroupId,
                    $keyword->keyword_text,
                );
                return $keywordDTO->toArray();
            })->toArray();

            $response = $this->sendAmazonBatchCreateRequest(
                '/sp/keywords',
                'keywords',
                $keywords->toArray(),
                'application/vnd.spKeyword.v3+json',
                'keyword',
                $keywordDTOs,
                AmazonAction::CREATE_KEYWORDS_BATCH,
                'keywordId'
            );

            return $response;

        } catch (\Exception $e) {
            Log::error('Failed to create keywords batch', [
                'keyword_count' => count($keywords),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function updateBid(array $data, $localId): array
    {
        return $this->sendAmazonUpdateRequest(
            '/sp/keywords',
            'keywords',
            $localId,
            'application/vnd.spKeyword.v3+json',
            'keyword',
            [
                'keywordId' => (string)$data['amazon_keyword_id'],
                'bid' => $data['bid']
            ],
            AmazonAction::UPDATE_KEYWORD_BID
        );
    }

    public function updateState(array $data, $localId): array
    {
        return $this->sendAmazonUpdateRequest(
            '/sp/keywords',
            'keywords',
            $localId,
            'application/vnd.spKeyword.v3+json',
            'keyword',
            [
                'keywordId' => (string)$data['amazon_keyword_id'],
                'state' => $data['state']
            ],
            AmazonAction::UPDATE_KEYWORD_STATE
        );
    }

    public function getKeywordSuggestions(string $amazonAdGroupId = null, ?array $targets = [], string $sortDimension = "DEFAULT", ?array $asins = []): array
    {

        $maxResults = 200;
        
        $adGroup = AdGroup::where('id', $amazonAdGroupId)->first();

        $payload = [
            "recommendationType" => "KEYWORDS_FOR_ADGROUP",
            "maxRecommendations" => $maxResults,
            "sortDimension" => $sortDimension,
        ];

        if(isset($adGroup)) {
            $payload['adGroupId'] = $adGroup->amazon_ad_group_id;
            if($adGroup->campaign && isset($adGroup->campaign->amazon_campaign_id)) {
                $payload['campaignId'] = $adGroup->campaign->amazon_campaign_id;
            }
        }

        if (!empty($targets)) {
            $targets = array_map(function ($target) {
                return [
                    "keyword" => $target['keyword'],
                    "matchType" => $target['matchType'],
                    "userSelectedKeyword" => true
                ];
            }, $targets);
            $payload['targets'] = $targets;

            $payload['maxRecommendations'] = 0;
        }
        if(!empty($asins)) {
            $payload['asins'] = $asins;
            $payload['recommendationType'] = "KEYWORDS_FOR_ASINS";
        } else {
            $payload['asins'] = $adGroup?->productAds->pluck('asin')->toArray();
        }

        if(empty($asins) && !isset($adGroup)) {
            return [];
        }

        $response = $this->adsApiClient->sendRequest(
            '/sp/targets/keywords/recommendations',
            $payload,
            'POST', 
            'application/vnd.spkeywordsrecommendation.v5+json'
        );

        return $response;
    }
}
