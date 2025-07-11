<?php

namespace App\AmazonAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\SbUser;
class NegativeProductTargeting extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tbl_amazon_negative_product_targeting';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'campaign_id',
        'ad_group_id',
        'amazon_negative_product_targeting_id',
        'state',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the campaign that owns the negative product targeting.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id', 'id');
    }

    /**
     * Get the ad group that owns the negative product targeting.
     */
    public function adGroup(): BelongsTo
    {
        return $this->belongsTo(AdGroup::class, 'ad_group_id', 'id');
    }

    /**
     * Get the expressions for this negative product targeting.
     */
    public function expressions(): HasMany
    {
        return $this->hasMany(NegativeProductTargetingExpression::class, 'negative_product_targeting_id', 'id');
    }

    public function getAmazonResponse()
    {
        return AmazonEventResponseLog::getResponsesForEntity('negativeProductTargeting', $this->id)->toArray();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(SbUser::class, 'user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PpcChangeLog::class, 'entity_id')
            ->where('entity_type', 'negativeProductTargeting');
    }
} 