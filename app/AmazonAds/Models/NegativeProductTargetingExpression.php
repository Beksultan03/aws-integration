<?php

namespace App\AmazonAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NegativeProductTargetingExpression extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tbl_amazon_negative_product_targeting_expressions';

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
        'negative_product_targeting_id',
        'type',
        'value',
    ];

    /**
     * Get the negative product targeting that owns the expression.
     */
    public function negativeProductTargeting(): BelongsTo
    {
        return $this->belongsTo(NegativeProductTargeting::class, 'negative_product_targeting_id', 'id');
    }
} 