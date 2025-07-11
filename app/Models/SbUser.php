<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class SbUser extends Authenticatable
{
    protected $table = 'tbl_sb_user';

    public $timestamps = false;

    public function getFullNameAttribute(): string
    {
        return $this->fname . ' ' . $this->lname;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function pmAccessRelations(): HasMany
    {
        return $this->hasMany(PmAccessRelation::class, 'user_id', 'id')->select('uar_id', 'access_id', 'permission_id');
    }
}
