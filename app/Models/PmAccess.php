<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PmAccess extends Authenticatable
{
    protected $table = 'tbl_pm_access';

    public $timestamps = false;

    public function pmAccessRelation(): BelongsTo
    {
        return $this->belongsTo(PmAccessRelation::class, 'access_id', 'uar_id');
    }

}
