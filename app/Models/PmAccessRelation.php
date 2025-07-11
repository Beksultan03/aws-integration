<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PmAccessRelation extends Authenticatable
{
    protected $table = 'tbl_pm_access_relation';

    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(SbUser::class, 'user_id', 'id');
    }

    public function pmAccess(): BelongsTo
    {
        return $this->belongsTo(PmAccess::class, 'access_id', 'access_id');
    }

}
