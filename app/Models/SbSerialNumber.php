<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SbSerialNumber extends Model
{
    protected $table = 'tbl_sb_serial_numbers';

    public const string TABLE_NAME = 'tbl_sb_serial_numbers';
    public function locationChangeHistory(): HasMany
    {
        return $this->hasMany(SerialNumberLocationChangeHistory::class, 'serial_id', 'serial_id');
    }
}
