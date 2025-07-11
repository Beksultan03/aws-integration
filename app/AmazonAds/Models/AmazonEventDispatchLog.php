<?php

namespace App\AmazonAds\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonEventDispatchLog extends Model
{
    use HasFactory;

    protected $table = 'tbl_amazon_event_dispatch_log';

    protected $fillable = ['event_type', 'payload', 'status'];

    protected $casts = [
        'payload' => 'array',
    ];
}
