<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{

    public static function withCustomSelectFields(array $selectFields = []): Builder
    {
        $items = self::query();
        if (!empty($selectFields)) {
            $items->select(...$selectFields);
        }

        return $items;
    }

}
