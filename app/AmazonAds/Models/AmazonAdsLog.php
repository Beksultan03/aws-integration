<?php

namespace App\AmazonAds\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class BaseProduct
 *
 * @property int $id
 * @property string $action
 * @property mixed $data
 */
class AmazonAdsLog extends Model
{
    public $table = 'tbl_sp_campaigns';

}
