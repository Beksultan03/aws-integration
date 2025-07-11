<?php

namespace App\AmazonAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\AmazonAds\Models\Campaign;
use App\Models\SbUser;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Keyword extends Model
{

    public const string MATCH_TYPE_EXACT = 'EXACT';
    public const string MATCH_TYPE_PHRASE = 'PHRASE';
    public const string MATCH_TYPE_BROAD = 'BROAD';
    protected $table = 'tbl_amazon_keyword';

    protected $fillable = [
        'campaign_id',
        'amazon_keyword_id',
        'user_id',
        'match_type',
        'state',
        'bid',
        'ad_group_id',
        'keyword_text',
        'marketplace_sku_reference_id'
    ];

    protected $casts = [
        'bid' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the campaign that owns the keyword target.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    /**
     * Scope a query to only include active keyword targets.
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

    public function adGroup(): BelongsTo
    {
        return $this->belongsTo(AdGroup::class, 'ad_group_id');
    }

    public function statistics()
    {
        return $this->hasMany(AmazonReportStatistic::class, 'entity_id')
            ->where('entity_type', 'keyword');
    }

    public function getType(): string
    {
        return 'keyword';
    }

    public function getAmazonResponse(): array
    {
        return AmazonEventResponseLog::getResponsesForEntity('keyword', $this->id)->toArray();
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(SbUser::class, 'user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PpcChangeLog::class, 'entity_id')
            ->where('entity_type', 'keyword');
    }
}
