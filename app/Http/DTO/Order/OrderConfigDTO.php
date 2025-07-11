<?php

namespace App\Http\DTO\Order;

use App\Http\DTO\BaseDTO;

class OrderConfigDTO extends BaseDTO
{
    public ?string $order_id;
    public ?string $page_number = null;
    public ?string $serial_number = null;
    public ?string $sku = null;
    public ?string $display_title = null;
    public array $details = [];
    public array $summary = [];
}
