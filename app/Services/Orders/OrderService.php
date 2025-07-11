<?php

namespace App\Services\Orders;

use App\Models\SbTechnicianOrder;

class OrderService
{
    public function getOrderTechnicianId($orderId)
    {
        return SbTechnicianOrder::query()->where('order_id', $orderId)->pluck('technician_id')->first();
    }
}
