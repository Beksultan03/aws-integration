<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $text
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string $type
 * @property int $user_id
 * @property int $entity_id
 */
class Log extends Model
{
    public $table = 'tbl_logs';
}
