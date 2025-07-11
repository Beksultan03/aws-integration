<?php

namespace App\AmazonAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductTargetingExpression extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tbl_amazon_product_targeting_expressions';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'product_targeting_id',
        'type',
        'value',
    ];

    /**
     * Get the product targeting that owns the expression.
     */
    public function productTargeting(): BelongsTo
    {
        return $this->belongsTo(ProductTargeting::class, 'product_targeting_id', 'id');
    }

    /**
     * Get the type of the entity.
     */
    public function getType(): string
    {
        return 'productTargeting';
    }

    /**
     * Get the Amazon API response logs for this entity.
     */
    public function getAmazonResponse(): array
    {
        return AmazonEventResponseLog::getResponsesForEntity('productTargeting', $this->product_targeting_id)->toArray();
    }
} 