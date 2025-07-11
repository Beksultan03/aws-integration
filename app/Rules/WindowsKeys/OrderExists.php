<?php

namespace App\Rules\WindowsKeys;

use App\Models\SbHistoryOrder;
use App\Models\SbOrder;
use Illuminate\Contracts\Validation\Rule;

class OrderExists implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        $existsInHistoryOrders = SbHistoryOrder::query()->where('orderid', $value)->exists();
        $existsInOrders = SbOrder::query()->where('orderid', $value)->exists();

        return $existsInHistoryOrders || $existsInOrders;
    }

    /**
     * Get the validation error message for the rule.
     *
     * @param string $attribute
     * @return string
     * @throws \Exception
     */
    public function message(): string
    {
       return 'The selected order ID does not exist in either history orders or orders.';
    }
}
