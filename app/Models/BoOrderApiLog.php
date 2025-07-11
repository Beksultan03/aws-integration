<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Class BoOrderApiLog
 *
 * @property int $id
 * @property string $orders
 * @property string $request
 * @property string $response
 * @property string $action
 * @property string $time
 */
class BoOrderApiLog extends Model
{
    public $table = 'bo_order_api_log';
    public $timestamps = false;

    protected $fillable = [
        'orders',
        'request',
        'response',
        'action',
        'time',
    ];

    /**
     * @param string $action
     * @param mixed $requestData
     * @param mixed $response
     * @return bool
     */
    public static function write(
        string $action,
        array $orders,
        mixed $requestData,
        mixed $response
    ): bool {
        $log = new static();
        $log->action = $action;
        try {
            $log->orders = json_encode($orders, 256);
            $log->request = json_encode($requestData, 256);
            $log->response = json_encode($response, 256);
        } catch (Throwable $throwable) {

        }
        $log->time = now();

        return $log->save();
    }

}
