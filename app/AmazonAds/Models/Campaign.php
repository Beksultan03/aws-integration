<?php

namespace App\AmazonAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Company;
use App\Models\SbUser;
use Illuminate\Support\Facades\Log;
/**
 * Class BaseProduct
 *
 * @property int $id
 * @property string $name
 * @property string $state
 * @property string $budget_type
 * @property string $targeting_type
 * @property string $bidding_strategy
 * @property string $user_id
 * @property int $portfolio_id
 */
class Campaign extends Model
{
    public const STATE_ENABLED = 'ENABLED';
    public const STATE_PAUSED = 'PAUSED';
    public const STATE_PROPOSED = 'PROPOSED';
    public const STATE_ARCHIVED = 'ARCHIVED';

    public $table = 'tbl_amazon_campaign';

    protected $fillable = [
        'id',
        'amazon_campaign_id',
        'name',
        'state',
        'type',
        'start_date',
        'end_date',
        'budget_type',
        'budget_amount',
        'targeting_type',
        'portfolio_id',
        'dynamic_bidding',
        'company_id',
        'user_id',
    ];

    protected $casts = [
        'budget_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function adGroups(): HasMany
    {
        return $this->hasMany(AdGroup::class, 'campaign_id');
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class, 'campaign_id');
    }

    public function negativeKeywords(): HasMany
    {
        return $this->hasMany(NegativeKeyword::class, 'campaign_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(ProductAd::class, 'campaign_id');
    }
    public function productTargetings(): HasMany
    {
        return $this->hasMany(ProductTargeting::class, 'campaign_id');
    }
    public function negativeProductTargetings(): HasMany
    {
        return $this->hasMany(NegativeProductTargeting::class, 'campaign_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PpcChangeLog::class, 'entity_id')->where('entity_type', 'campaign');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(SbUser::class, 'user_id');
    }

    public function statistics()
    {
        return $this->hasMany(AmazonReportStatistic::class, 'entity_id')
            ->where('entity_type', 'campaign');
    }

    public function getType(): string
    {
        return 'campaign';
    }

    public function getAmazonResponse(): array
    {
        return AmazonEventResponseLog::getResponsesForEntity('campaign', $this->id)->toArray();
    }

    public function getLogs()
    {
        $logs = collect();

        // Get campaign logs
        $logs = $logs->merge(
            $this->logs()
                ->where('action', '!=', 'created')
                ->orderBy('changed_at', 'desc')
                ->get()
        );

        // Load all related models
        $this->load([
            'adGroups',
            'keywords',
            'negativeKeywords',
            'productTargetings',
            'negativeProductTargetings',
            'products'
        ]);

        // Get ad group logs
        if ($this->adGroups) {
            $this->adGroups->each(function ($adGroup) use (&$logs) {
                $logs = $logs->merge(
                    $adGroup->logs()
                        ->orderBy('changed_at', 'desc')
                        ->get()
                );
            });
        }

        // Get product ad logs
        if ($this->products) {
            $this->products->each(function ($productAd) use (&$logs) {
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
        if ($this->productTargetings) {
            $this->productTargetings->each(function ($productTargeting) use (&$logs) {
                $logs = $logs->merge(
                    $productTargeting->logs()
                        ->orderBy('changed_at', 'desc')
                        ->get()
                );
            });
        }

        // Get negative product targeting logs
        if ($this->negativeProductTargetings) {
            $this->negativeProductTargetings->each(function ($negativeProductTargeting) use (&$logs) {
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
