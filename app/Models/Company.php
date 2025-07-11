<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Company extends Authenticatable
{

    public const array AVAILABLE_COMPANIES = [170, 164];

    protected $table = 'tbl_company';

    public $timestamps = false;
}
