<?php

namespace App\AmazonAds\Services\Amazon;

use App\AmazonAds\Exceptions\AmazonAdsException;
use App\AmazonAds\Models\NegativeKeyword;
use App\AmazonAds\Models\AdGroup;
use App\AmazonAds\Services\AdsApiClient;
use App\Models\Company;
use Illuminate\Support\Facades\Log;
use App\AmazonAds\Helpers\DateFormatter;
use App\AmazonAds\Enums\AmazonAction;
use App\AmazonAds\Traits\AmazonApiTrait;
use App\AmazonAds\Http\DTO\Amazon\NegativeKeyword\CreateDTO;
use App\AmazonAds\Models\Campaign;

class ApiNegativeKeywordService
{
    use AmazonApiTrait;

    public function __construct(
        private readonly AdsApiClient $adsApiClient
    ) {}

    /**
     * Sync all negative keywords from Amazon Advertising API
     */
    public function syncNegativeKeywords(int $companyId): array
    {
        try {
            $allNegativeKeywords = [];
            $nextToken = null;

            $company = Company::where('company_id', $companyId)->first();
            if (!$company) {
                throw new AmazonAdsException("Company not found");
            }

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
                    '/sp/negativeKeywords/list',
                    $payload,
                    'POST',
                    'application/vnd.spNegativeKeyword.v3+json',
                    $companyId
                );

                if (!empty($response['negativeKeywords'])) {
                    $this->processNegativeKeywordBatch($response['negativeKeywords'], $companyId, $adGroupIdMap);
                    $allNegativeKeywords = array_merge($allNegativeKeywords, $response['negativeKeywords']);
                }

                $nextToken = $response['nextToken'] ?? null;

            } while ($nextToken);


            return [
                'success' => true,
                'message' => 'Negative keywords synced successfully',
                'count' => count($allNegativeKeywords)
            ];

        } catch (AmazonAdsException $e) {
            Log::error('Failed to sync negative keywords: ' . $e->getMessage());
            throw new AmazonAdsException("Failed to sync negative keywords: " . $e->getMessage());
        }
    }

    /**
     * Process a batch of negative keywords from Amazon API
     */
    private function processNegativeKeywordBatch(array $negativeKeywords, int $companyId, array $adGroupIdMap): void
    {
        $existingNegativeKeywords = NegativeKeyword::whereHas('campaign', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })->pluck('amazon_negative_keyword_id')->toArray();

        $toUpsert = [];

        foreach ($negativeKeywords as $negativeKeywordData) {
            if (!isset($adGroupIdMap[$negativeKeywordData['adGroupId']]['id'])) {
                Log::warning('Ad group not found for negative keyword', [
                    'amazon_ad_group_id' => $negativeKeywordData['adGroupId'],
                    'negative_keyword_id' => $negativeKeywordData['keywordId']
                ]);
                continue;
            }

            $data = [
                'amazon_negative_keyword_id' => $negativeKeywordData['keywordId'],
                'campaign_id' => $adGroupIdMap[$negativeKeywordData['adGroupId']]['campaign_id'],
                'ad_group_id' => $adGroupIdMap[$negativeKeywordData['adGroupId']]['id'],
                'keyword_text' => $negativeKeywordData['keywordText'],
                'match_type' => $negativeKeywordData['matchType'],
                'state' => $negativeKeywordData['state'],
                'created_at' => DateFormatter::formatDateTime($negativeKeywordData['creationDateTime'] ?? null),
                'updated_at' => DateFormatter::formatDateTime($negativeKeywordData['lastUpdatedDateTime'] ?? null),
            ];

            $toUpsert[] = $data;
        }

        if (!empty($toUpsert)) {
            NegativeKeyword::upsert(
                $toUpsert,
                ['amazon_negative_keyword_id', 'ad_group_id'],
                [
                    'campaign_id',
                    'ad_group_id',
                    'keyword_text',
                    'match_type',
                    'state',
                    'created_at',
                    'updated_at',
                ]
            );
        }
    }

    /**
     * Create batch of negative keywords in Amazon
     */
    public function createBatch($keywords, string $amazonCampaignId, string $amazonAdGroupId): array
    {
        try {
            // Prepare data for Amazon API
            
            $negativeKeywordDTOs = [];
            if (!empty($keywords)) {
                $negativeKeywordDTOs = $keywords->map(function ($negKeyword) use ($amazonCampaignId, $amazonAdGroupId) {
                    $negKeywordDTO = new CreateDTO(
                        $negKeyword->keyword_text,
                        $negKeyword->match_type,
                        Campaign::STATE_ENABLED,
                        $negKeyword->id,
                        $amazonAdGroupId,
                        $amazonCampaignId,
                    );
                    return $negKeywordDTO->toArray();
                })->toArray();
            }

            return $this->sendAmazonBatchCreateRequest(
                '/sp/negativeKeywords',
                'negativeKeywords',
                $keywords->toArray(),
                'application/vnd.spNegativeKeyword.v3+json',
                'negativeKeyword',
                $negativeKeywordDTOs,
                AmazonAction::CREATE_NEGATIVE_KEYWORDS_BATCH,
                'negativeKeywordId'
            );

        } catch (\Exception $e) {
            Log::error('Failed to create negative keywords batch', [
                'keyword_count' => count($keywords),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function updateState(array $data, string $negativeKeywordId): array
    {
        return $this->sendAmazonUpdateRequest(
            '/sp/negativeKeywords',
            'negativeKeywords',
            $negativeKeywordId,
            'application/vnd.spNegativeKeyword.v3+json',
            'negativeKeyword',
            [
                'keywordId' => (string)$data['amazon_negative_keyword_id'],
                'state' => $data['state']
            ],
            AmazonAction::UPDATE_NEGATIVE_KEYWORD_STATE
        );
    }
} 