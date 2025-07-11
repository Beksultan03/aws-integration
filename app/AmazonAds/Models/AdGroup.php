<?php

namespace App\AmazonAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\SbUser;
class AdGroup extends Model
{
    protected $table = 'tbl_amazon_ad_group';

    protected $fillable = [
        'campaign_id',
        'amazon_ad_group_id',
        'name',
        'state',
        'default_bid',
        'company_id',
        'user_id'
    ];

    protected $casts = [
        'default_bid' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the campaign that owns the ad group.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class, 'ad_group_id');
    }

    public function negativeKeywords(): HasMany
    {
        return $this->hasMany(NegativeKeyword::class, 'ad_group_id');
    }

    public function productAds(): HasMany
    {
        return $this->hasMany(ProductAd::class, 'ad_group_id');
    }

    public function productTargeting(): HasMany
    {
        return $this->hasMany(ProductTargeting::class, 'ad_group_id');
    }

    public function negativeProductTargeting(): HasMany
    {
        return $this->hasMany(NegativeProductTargeting::class, 'ad_group_id');
    }

    public function statistics()
    {
        return $this->hasMany(AmazonReportStatistic::class, 'entity_id')
            ->where('entity_type', $this->getTable());
    }

    public function getAmazonResponse(): array
    {
        return AmazonEventResponseLog::getResponsesForEntity('adGroup', $this->id)->toArray();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(SbUser::class, 'user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PpcChangeLog::class, 'entity_id')
            ->where('entity_type', 'adGroup')
            ->where('action', '!=', 'created');
    }

    public function getLogs()
    {
        $logs = collect();

        // Get campaign logs
        $logs = $logs->merge(
            $this->logs()
                ->orderBy('changed_at', 'desc')
                ->get()
        );

        // Load all related models
        $this->load([
            'keywords',
            'negativeKeywords',
            'productTargeting',
            'negativeProductTargeting',
            'productAds'
        ]);

        // Get product ad logs
        if ($this->productAds) {
            $this->productAds->each(function ($productAd) use (&$logs) {
                $logs = $logs->merge(
                    $productAd->logs()
                        ->orderBy('changed_at', 'desc')
                        ->get()
                );
            });
        }

        // Get keyword logs
        if ($this->keywords) {
            $this->keywords->each(function ($keyword) use (&$logs) {
                $logs = $logs->merge(
                    $keyword->logs()
                        ->orderBy('changed_at', 'desc')
                        ->get()
                );
            });
        }

        // Get negative keyword logs
        if ($this->negativeKeywords) {
            $this->negativeKeywords->each(function ($negativeKeyword) use (&$logs) {
                $logs = $logs->merge(
                    $negativeKeyword->logs()
                        ->orderBy('changed_at', 'desc')
                        ->get()
                );
            });
        }

        // Get product targeting logs
        if ($this->productTargeting) {
            $this->productTargeting->each(function ($productTargeting) use (&$logs) {
                $logs = $logs->merge(
                    $productTargeting->logs()
                        ->orderBy('changed_at', 'desc')
                        ->get()
                );
            });
        }

        // Get negative product targeting logs
        if ($this->negativeProductTargeting) {
            $this->negativeProductTargeting->each(function ($negativeProductTargeting) use (&$logs) {
                $logs = $logs->merge(
                    $negativeProductTargeting->logs()
                        ->orderBy('changed_at', 'desc')
                        ->get()
                );
            });
        }

        return $logs->sortByDesc('changed_at')
            ->values();
    }
}
