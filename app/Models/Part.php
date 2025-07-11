<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Part
 *
 * @property int $id
 * @property string part_type_id
 * @property string name
 */
class Part extends Model
{
    protected $table = 'tbl_parts';
}
