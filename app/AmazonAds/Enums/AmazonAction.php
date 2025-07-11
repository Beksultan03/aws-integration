<?php

namespace App\AmazonAds\Enums;

enum AmazonAction: string
{

    case CREATE_CAMPAIGN = 'createCampaign';
    case UPDATE_CAMPAIGN = 'updateCampaign';
    case DELETE_CAMPAIGN = 'deleteCampaign';
    case UPDATE_CAMPAIGN_BID = 'updateCampaignBid';
    case UPDATE_CAMPAIGN_STATE = 'updateCampaignState';
    case DELETE_CAMPAIGNS_BATCH = 'deleteCampaignsBatch';
    case CREATE_CAMPAIGN_COMPLETE = 'createCampaignComplete';
    case CREATE_KEYWORD = 'createKeyword';
    case UPDATE_KEYWORD = 'updateKeyword';
    case CREATE_AD_GROUP = 'createAdGroup';
    case UPDATE_AD_GROUP = 'updateAdGroup';
    case UPDATE_AD_GROUP_BID = 'updateAdGroupBid';
    case UPDATE_AD_GROUP_STATE = 'updateAdGroupState';
    case CREATE_KEYWORDS_BATCH = 'createKeywordsBatch';
    case CREATE_PRODUCT_AD = 'createProductAd';
    case CREATE_PRODUCT_ADS_BATCH = 'createProductAdsBatch';
    case CREATE_NEGATIVE_KEYWORDS_BATCH = 'createNegativeKeywordsBatch';
    case UPDATE_KEYWORD_BID = 'updateKeywordBid';
    case UPDATE_KEYWORD_STATE = 'updateKeywordState';
    case UPDATE_PRODUCT_AD_STATE = 'updateProductAdState';
    case UPDATE_NEGATIVE_KEYWORD_STATE = 'updateNegativeKeywordState';
    case CREATE_PRODUCT_TARGETING_BATCH = 'createProductTargetingBatch';
    case UPDATE_PRODUCT_TARGETING_BID = 'updateProductTargetingBid';
    case UPDATE_PRODUCT_TARGETING_STATE = 'updateProductTargetingState';
    case CREATE_NEGATIVE_PRODUCT_TARGETING_BATCH = 'createNegativeProductTargetingBatch';
    case UPDATE_NEGATIVE_PRODUCT_TARGETING_STATE = 'updateNegativeProductTargetingState';
    
}
