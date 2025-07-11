<?php

namespace App\AmazonAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\AmazonAds\Models\Campaign;
use App\Models\SbUser;
use Illuminate\Database\Eloquent\Relations\HasMany;
class NegativeKeyword extends Model
{

    public const string MATCH_TYPE_EXACT = 'NEGATIVE_EXACT';
    public const string MATCH_TYPE_PHRASE = 'NEGATIVE_PHRASE';
    public const string MATCH_TYPE_BROAD = 'NEGATIVE_BROAD';

    protected $table = 'tbl_amazon_negative_keyword';

    protected $fillable = [
        'campaign_id',
        'amazon_negative_keyword_id',
        'ad_group_id',
        'match_type',
        'state',
        'keyword_text',
        'user_id'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the campaign that owns the negative keyword.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }
    
    /**
     * Scope a query to only include active negative keywords.
     */
    public function scopeActive($query)
    {
        return $query->where('state', Campaign::STATE_ENABLED);
    }

    /**
     * Scope a query to filter by campaign.
     */
    public function scopeByCampaign($query, $campaignId)
    {
        return $query->where('campaign_id', $campaignId);
    }

    public function getAmazonResponse(): array
    {
        return AmazonEventResponseLog::getResponsesForEntity('negativeKeyword', $this->id)->toArray();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(SbUser::class, 'user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PpcChangeLog::class, 'entity_id')
            ->where('entity_type', 'negativeKeyword');
    }
} 