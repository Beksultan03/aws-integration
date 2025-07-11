<?php

namespace App\Http\DTO\Product;

use App\Http\DTO\BaseDTO;

class ProductDetailsDTO extends BaseDTO
{
    public string $serial_number;
    public ?string $order_id = null;
    public ?string $raid = null;
    public ?string $display_title = null;
    public ?string $ram = null;
    public ?string $storage = null;
    public ?string $gpu = null;
    public ?string $os = null;
    public ?string $cpu = null;

    public function __construct(string $serial_number)
    {
        $this->serial_number = $serial_number;
    }

}
