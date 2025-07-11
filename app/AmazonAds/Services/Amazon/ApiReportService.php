<?php

namespace App\AmazonAds\Services\Amazon;

use App\AmazonAds\Exceptions\AmazonAdsException;
use App\AmazonAds\Services\AdsApiClient;
use App\Models\Company;
use Illuminate\Support\Facades\Log;
use App\AmazonAds\Models\Keyword;
use App\AmazonAds\Models\Campaign;
class ApiReportService
{
    protected AdsApiClient $adsApiClient;

    public function __construct(AdsApiClient $adsApiClient)
    {
        $this->adsApiClient = $adsApiClient;
    }

    /**
     * Get report configuration based on type and time granularity
     */
    private function getReportConfiguration(string $reportType, bool $isSummary = false): array
    {
        if (!isset($reportType)) {
            throw new AmazonAdsException("Invalid report type: {$reportType}");
        }

        $reportTypeIds = [
            'campaign' => [
                'reportTypeId' => 'spCampaigns',
                'adProduct' => 'SPONSORED_PRODUCTS'
            ],
            'keyword' => [
                'reportTypeId' => 'spTargeting',
                'adProduct' => 'SPONSORED_PRODUCTS'
            ],
            'productAd' => [
                'reportTypeId' => 'spAdvertisedProduct',
                'adProduct' => 'SPONSORED_PRODUCTS'
            ],
            'searchTerm' => [
                'reportTypeId' => 'spSearchTerm',
                'adProduct' => 'SPONSORED_PRODUCTS'
            ],
            'productTargeting' => [
                'reportTypeId' => 'sbTargeting',
                'adProduct' => 'SPONSORED_BRANDS'
            ]
        ];

        $entityConfigs = [
            'campaign' => [
                'groupBy' => ['campaign'],
                'filters' => [
                    [
                        'field' => 'campaignStatus',
                        'values' => [Campaign::STATE_ENABLED, Campaign::STATE_PAUSED, Campaign::STATE_ARCHIVED]
                    ]
                ]
            ],
            'keyword' => [
                'groupBy' => ['targeting'],
                'filters' => [
                    [
                        'field' => 'keywordType',
                        'values' => [Keyword::MATCH_TYPE_EXACT, Keyword::MATCH_TYPE_PHRASE, Keyword::MATCH_TYPE_BROAD]
                    ]
                ]
            ],
            'productAd' => [
                'groupBy' => ['advertiser'],
                'filters' => []
            ],
            'searchTerm' => [
                'groupBy' => ['searchTerm'],
                'filters' => [
                    [
                        'field' => 'keywordType',
                        'values' => [Keyword::MATCH_TYPE_EXACT, Keyword::MATCH_TYPE_PHRASE, Keyword::MATCH_TYPE_BROAD]
                    ]
                ]
            ],
            'productTargeting' => [
                'groupBy' => ['targeting'],
                'filters' => [
                    [
                        'field' => 'keywordType',
                        'values' => ['TARGETING_EXPRESSION', 'TARGETING_EXPRESSION_PREDEFINED']
                    ]
                ]
            ]
        ];
        
        $requiredColumns = [
            'campaign' => ['attributedSalesSameSku1d', 'campaignBiddingStrategy', 'roasClicks14d', 'unitsSoldClicks1d', 'attributedSalesSameSku7d', 'attributedSalesSameSku14d', 'royaltyQualifiedBorrows', 'sales1d', 'sales7d', 'addToList', 'attributedSalesSameSku30d', 'purchasesSameSku14d', 'kindleEditionNormalizedPagesRoyalties14d', 'purchasesSameSku1d', 'spend', 'unitsSoldSameSku1d', 'purchases1d', 'purchasesSameSku7d', 'unitsSoldSameSku7d', 'purchases7d', 'unitsSoldSameSku30d', 'cost', 'costPerClick', 'unitsSoldClicks14d', 'retailer', 'sales14d', 'sales30d', 'clickThroughRate', 'impressions', 'kindleEditionNormalizedPagesRead14d', 'purchasesSameSku30d', 'purchases14d', 'unitsSoldClicks30d', 'qualifiedBorrows', 'acosClicks14d', 'purchases30d', 'clicks', 'unitsSoldClicks7d', 'unitsSoldSameSku14d', 'campaignRuleBasedBudgetAmount', 'campaignBudgetCurrencyCode', 'campaignId', 'campaignApplicableBudgetRuleId', 'campaignBudgetType', 'topOfSearchImpressionShare', 'campaignStatus', 'campaignName', 'campaignApplicableBudgetRuleName', 'campaignBudgetAmount'],
            'keyword' => ['impressions', 'addToList', 'qualifiedBorrows', 'royaltyQualifiedBorrows', 'clicks', 'costPerClick', 'clickThroughRate', 'cost', 'purchases1d', 'purchases7d', 'purchases14d', 'purchases30d', 'purchasesSameSku1d', 'purchasesSameSku7d', 'purchasesSameSku14d', 'purchasesSameSku30d', 'unitsSoldClicks1d', 'unitsSoldClicks7d', 'unitsSoldClicks14d', 'unitsSoldClicks30d', 'sales1d', 'sales7d', 'sales14d', 'sales30d', 'attributedSalesSameSku1d', 'attributedSalesSameSku7d', 'attributedSalesSameSku14d', 'attributedSalesSameSku30d', 'unitsSoldSameSku1d', 'unitsSoldSameSku7d', 'unitsSoldSameSku14d', 'unitsSoldSameSku30d', 'kindleEditionNormalizedPagesRead14d', 'kindleEditionNormalizedPagesRoyalties14d', 'salesOtherSku7d', 'unitsSoldOtherSku7d', 'acosClicks7d', 'acosClicks14d', 'roasClicks7d', 'roasClicks14d', 'keywordId', 'keyword', 'campaignBudgetCurrencyCode', 'portfolioId', 'campaignName', 'campaignId', 'campaignBudgetType', 'campaignBudgetAmount', 'campaignStatus', 'keywordBid', 'adGroupName', 'adGroupId', 'keywordType', 'matchType', 'targeting', 'topOfSearchImpressionShare'],
            'productAd' => [ 'campaignName', 'campaignId', 'adGroupName', 'adGroupId', 'adId', 'addToList', 'qualifiedBorrows', 'royaltyQualifiedBorrows', 'portfolioId', 'impressions', 'clicks', 'costPerClick', 'clickThroughRate', 'cost', 'spend', 'campaignBudgetCurrencyCode', 'campaignBudgetAmount', 'campaignBudgetType', 'campaignStatus', 'advertisedAsin', 'advertisedSku', 'purchases1d', 'purchases7d', 'purchases14d', 'purchases30d', 'purchasesSameSku1d', 'purchasesSameSku7d', 'purchasesSameSku14d', 'purchasesSameSku30d', 'unitsSoldClicks1d', 'unitsSoldClicks7d', 'unitsSoldClicks14d', 'unitsSoldClicks30d', 'sales1d', 'sales7d', 'sales14d', 'sales30d', 'attributedSalesSameSku1d', 'attributedSalesSameSku7d', 'attributedSalesSameSku14d', 'attributedSalesSameSku30d', 'salesOtherSku7d', 'unitsSoldSameSku1d', 'unitsSoldSameSku7d', 'unitsSoldSameSku14d', 'unitsSoldSameSku30d', 'unitsSoldOtherSku7d', 'kindleEditionNormalizedPagesRead14d', 'kindleEditionNormalizedPagesRoyalties14d', 'acosClicks7d', 'acosClicks14d', 'roasClicks7d', 'roasClicks14d'],
            'searchTerm' => ['attributedSalesSameSku1d', 'roasClicks14d', 'unitsSoldClicks1d', 'matchType', 'attributedSalesSameSku14d', 'unitsSoldOtherSku14d', 'sales7d', 'attributedSalesSameSku30d', 'salesOtherSku14d', 'kindleEditionNormalizedPagesRoyalties14d', 'searchTerm', 'unitsSoldSameSku1d', 'campaignStatus', 'keyword', 'purchasesSameSku7d', 'salesOtherSku7d', 'campaignBudgetAmount', 'purchases7d', 'unitsSoldSameSku30d', 'costPerClick', 'unitsSoldClicks14d', 'adGroupName', 'campaignId', 'clickThroughRate', 'purchaseClickRate14d', 'kindleEditionNormalizedPagesRead14d', 'unitsSoldClicks30d', 'qualifiedBorrows', 'acosClicks14d', 'campaignBudgetCurrencyCode', 'portfolioId', 'unitsSoldClicks7d', 'unitsSoldSameSku14d', 'roasClicks7d', 'keywordId', 'attributedSalesSameSku7d', 'royaltyQualifiedBorrows', 'sales1d', 'adGroupId', 'addToList', 'keywordBid', 'targeting', 'purchasesSameSku14d', 'unitsSoldOtherSku7d', 'spend', 'purchasesSameSku1d', 'campaignBudgetType', 'keywordType', 'purchases1d', 'unitsSoldSameSku7d', 'cost', 'retailer', 'sales14d', 'acosClicks7d', 'sales30d', 'impressions', 'purchasesSameSku30d', 'purchases14d', 'purchases30d', 'clicks', 'campaignName', 'adKeywordStatus'],
            'searchPurchased' => ['attributedSalesSameSku1d', 'roasClicks14d', 'unitsSoldClicks1d', 'matchType', 'attributedSalesSameSku14d', 'unitsSoldOtherSku14d', 'sales7d', 'attributedSalesSameSku30d', 'salesOtherSku14d', 'kindleEditionNormalizedPagesRoyalties14d', 'searchTerm', 'unitsSoldSameSku1d', 'campaignStatus', 'keyword', 'purchasesSameSku7d', 'salesOtherSku7d', 'campaignBudgetAmount', 'purchases7d', 'unitsSoldSameSku30d', 'costPerClick', 'unitsSoldClicks14d', 'adGroupName', 'campaignId', 'clickThroughRate', 'purchaseClickRate14d', 'kindleEditionNormalizedPagesRead14d', 'unitsSoldClicks30d', 'qualifiedBorrows', 'acosClicks14d', 'campaignBudgetCurrencyCode', 'portfolioId', 'unitsSoldClicks7d', 'unitsSoldSameSku14d', 'roasClicks7d', 'keywordId', 'attributedSalesSameSku7d', 'royaltyQualifiedBorrows', 'sales1d', 'adGroupId', 'addToList', 'keywordBid', 'targeting', 'purchasesSameSku14d', 'unitsSoldOtherSku7d', 'spend', 'purchasesSameSku1d', 'campaignBudgetType', 'keywordType', 'purchases1d', 'unitsSoldSameSku7d', 'cost', 'retailer', 'sales14d', 'acosClicks7d', 'sales30d', 'impressions', 'purchasesSameSku30d', 'purchases14d', 'purchases30d', 'clicks', 'campaignName', 'adKeywordStatus'],
            'productTargeting' => ['adGroupId',  'campaignId', 'clicks', 'cost', 'date', 'impressions', 'keywordId', 'keywordText', 'keywordType', 'matchType', 'purchases', 'purchasesClicks', 'sales', 'salesClicks', 'salesPromoted', 'targetingExpression', 'targetingId', 'targetingText', 'targetingType', 'topOfSearchImpressionShare', 'unitsSold']
        ];

        if($isSummary) {
            $metrics = array_merge($requiredColumns[$reportType], ['startDate', 'endDate']);
        } else {
            $metrics = array_push($requiredColumns[$reportType], 'date');
        }

        if (isset($requiredColumns[$reportType])) {
            $metrics = $requiredColumns[$reportType];
        }

        Log::info('Report configuration', [
            'isSummary' => $isSummary,
            'reportType' => $reportType,
            'metrics' => $metrics,
        ]);

        return [
            'reportTypeId' => $reportTypeIds[$reportType]['reportTypeId'],
            'timeUnit' => $isSummary ? 'SUMMARY' : 'DAILY',
            'format' => 'GZIP_JSON',
            'name' => "{$reportType}_" . ($isSummary ? 'summary' : 'daily') . "_report_" . uniqid(),
            'adProduct' => $reportTypeIds[$reportType]['adProduct'],
            'columns' => array_values(array_unique($metrics)),
            'groupBy' => $entityConfigs[$reportType]['groupBy'],
            'filters' => $entityConfigs[$reportType]['filters']
        ];
    }

    /**
     * Generate report for any entity type
     */
    public function generateReport(int $companyId, string $startDate, string $endDate, string $reportType, bool $isSummary = false): string
    {
        try {
            $company = Company::where('company_id', $companyId)->first();
            if (!$company) {
                throw new AmazonAdsException("Company not found");
            }

            $this->adsApiClient->scope = $company->amazon_profile_id;

            Log::info('Report generation started', [
                'reportType' => $reportType,
                'isSummary' => $isSummary,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'company' => $company
            ]);

            $maxRetries = 3;
            $attempt = 0;
            $reportId = null;

            while ($attempt < $maxRetries && !$reportId) {
                try {
                    $timestamp = time();
                    $uniqueId = uniqid($timestamp . '_', true);
                    
                    $reportData = [
                        'startDate' => $startDate,
                        'endDate' => $endDate,
                        'configuration' => $this->getReportConfiguration($reportType, $isSummary)
                    ];

                    Log::info('Report request data', ['data' => $reportData]);

                    if ($attempt > 0) {
                        $waitTime = 5 * pow(2, $attempt);
                        Log::info("Retrying report creation", [
                            'attempt' => $attempt + 1,
                            'waitTime' => $waitTime
                        ]);
                        sleep($waitTime);
                    }

                    $response = $this->adsApiClient->sendRequest(
                        '/reporting/reports',
                        $reportData,
                        'POST',
                        'application/vnd.createasyncreportrequest.v3+json',
                        $companyId
                    );
                    
                    $reportId = $response['reportId'];
                    Log::info('Report generation initiated', [
                        'reportId' => $reportId,
                        'attempt' => $attempt + 1,
                        'isSummary' => $isSummary
                    ]);
                    
                } catch (AmazonAdsException $e) {
                    $errorMessage = $e->getMessage();
                    $attempt++;
                    
                    if (strpos($errorMessage, '425') !== false) {
                        preg_match('/duplicate of : ([a-f0-9-]+)/', $errorMessage, $matches);
                        if (isset($matches[1])) {
                            $reportId = $matches[1];
                            Log::info('Using existing report', ['reportId' => $reportId]);
                            break;
                        }
                    }

                    Log::warning('Report creation attempt failed', [
                        'attempt' => $attempt,
                        'error' => $errorMessage
                    ]);

                    if ($attempt >= $maxRetries) {
                        throw new AmazonAdsException("Failed to create report after {$maxRetries} attempts: " . $errorMessage);
                    }
                }
            }

            if (!$reportId) {
                throw new AmazonAdsException("Failed to create report after {$maxRetries} attempts");
            }

            return $reportId;

        } catch (\Exception $e) {
            Log::error('Failed to generate report: ' . $e->getMessage());
            throw new AmazonAdsException("Failed to generate report: " . $e->getMessage());
        }
    }

    /**
     * Get report by ID
     */
    public function getReport(string $reportId, int $id, int $companyId): array
    {
        try {

            $response = $this->adsApiClient->sendRequest(
                "/reporting/reports/{$reportId}",
                [],
                'GET',
                'application/vnd.createasyncreportrequest.v3+json',
                $companyId
            );

            if ($response['status'] === 'COMPLETED' && isset($response['url'])) {
                try {
                    $reportContent = file_get_contents($response['url']);
                    $decompressed = gzdecode($reportContent);
                    $reportData = json_decode($decompressed, true);
                    return [
                        'status' => 'COMPLETED',
                        'data' => $reportData,
                        'metadata' => [
                            'id' => $id,
                            'reportId' => $response['reportId'],
                            'name' => $response['name'],
                            'startDate' => $response['startDate'],
                            'endDate' => $response['endDate'],
                            'creationDate' => $response['createdAt'],
                            'configuration' => $response['configuration']
                        ]
                    ];
                } catch (\Exception $e) {
                    Log::error('Failed to download report content', [
                        'reportId' => $reportId,
                        'error' => $e->getMessage()
                    ]);
                    
                    return [
                        'status' => 'ERROR',
                        'error' => 'Failed to download report content',
                        'metadata' => [
                            'reportId' => $response['reportId'],
                            'name' => $response['name']
                        ]
                    ];
                }
            }

            if ($response['status'] === 'PENDING') {
                return [
                    'status' => 'PENDING',
                    'metadata' => [
                        'reportId' => $response['reportId'],
                        'name' => $response['name']
                    ]
                ];
            }

            return [
                'status' => $response['status'],
                'metadata' => [
                    'reportId' => $response['reportId'],
                    'name' => $response['name'],
                    'startDate' => $response['startDate'],
                    'endDate' => $response['endDate'],
                    'creationDate' => $response['createdAt'],
                    'configuration' => $response['configuration']
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get report', [
                'reportId' => $reportId,
                'error' => $e->getMessage()
            ]);
            throw new AmazonAdsException("Failed to get report: " . $e->getMessage());
        }
    }
} 