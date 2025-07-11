<?php

namespace App\Rules\BlueOcean;

use App\Models\Kit;
use App\Models\SbOrder;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

readonly class ActiveUnshippedOrdersExists implements ValidationRule
{
    public const RELEASE_ORDERS = 'release';
    public const HIDE_ORDERS = 'hide';

    public function __construct(private ?string $type = null) {}
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $existingOrders = SbOrder::query()
            ->select('orderid', 'statuscode')
            ->whereIn('orderid', $value);

        $blueOceanOrders = SbOrder::query()
            ->select('orderid')
            ->whereIn('orderid', $value);

        $error = '';

        if  ($this->type === self::HIDE_ORDERS) {
            $blueOceanOrders->whereNot('kit_status', Kit::KIT_STATUS_BLUE_OCEAN);

            $error = "You can't hide orders which doesn't have status blue ocean";
        }

        if ($this->type === self::RELEASE_ORDERS) {
            $blueOceanOrders->where('kit_status', Kit::KIT_STATUS_BLUE_OCEAN);

            $error = "You can't release order which already has status blue ocean";
        }

        if ($blueOceanOrders->exists()) {
            $fail($error);
        }

        $existingOrders = $existingOrders->get();

        $shippedOrders = $existingOrders->filter(fn(SbOrder $order) => $order->statuscode !== 0);
        $missingOrders = collect(
            array_diff(
                $value,
                $existingOrders->pluck('orderid')->toArray()
            )
        );

        if ($missingOrders->isNotEmpty()) {
            $fail('Orders with IDs are missing: ' . $missingOrders->implode(', '));
        }

        if ($shippedOrders->isNotEmpty()) {
            $fail('Orders with IDs are not unshipped: ', $shippedOrders->implode(', '));
        }
    }
}
