<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhitelistedIp extends Model
{

    protected $table = 'tbl_whitelisted_ips';

    protected $fillable = [
        'ip_address',
        'name',
    ];
}
