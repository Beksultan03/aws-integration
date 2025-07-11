<?php

namespace App\AmazonAds\Models;

use Illuminate\Database\Eloquent\Model;

class Portfolio extends Model
{
    protected $table = 'tbl_amazon_portfolio';

    protected $fillable = [
        'amazon_portfolio_id',
        'name',
        'state',
        'in_budget',
        'company_id',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
} 