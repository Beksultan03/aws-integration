<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrustedEmail extends Model
{
    protected $table = 'tbl_trusted_emails';

    protected $fillable = [
        'email',
        'password',
    ];
}
