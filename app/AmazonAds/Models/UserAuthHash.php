<?php

namespace App\AmazonAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\SbUser;

class UserAuthHash extends Model
{
    protected $table = 'tbl_user_auth_hashes';

    protected $fillable = [
        'user_id',
        'hash',
        'created_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(SbUser::class, 'user_id');
    }

} 