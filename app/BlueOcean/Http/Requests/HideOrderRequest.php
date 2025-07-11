<?php

namespace App\BlueOcean\Http\Requests;

use App\Rules\BlueOcean\ActiveUnshippedOrdersExists;
use Illuminate\Foundation\Http\FormRequest;

class HideOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'orders' => [
                'required',
                'array',
                new ActiveUnshippedOrdersExists(ActiveUnshippedOrdersExists::HIDE_ORDERS)
            ],
        ];
    }
}
