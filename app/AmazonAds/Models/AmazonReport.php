<?php

namespace App\AmazonAds\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonReport extends Model
{
    protected $table = 'tbl_amazon_report';

    protected $fillable = [
        'company_id',
        'report_id',
        'report_type',
        'start_date',
        'end_date',
        'status',
        'attempts',
        'last_attempt_at',
        'processed_at',
        'error_message'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'last_attempt_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }
} 