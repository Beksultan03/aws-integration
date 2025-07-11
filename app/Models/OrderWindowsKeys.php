<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderWindowsKeys extends Model
{
    protected $table = 'tbl_order_windows_activation_keys';

    protected $fillable = ['order_id', 'key_type', 'quantity'];
}
