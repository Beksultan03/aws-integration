<?php

namespace App\BlueOcean\Http\Requests;

use App\Rules\BlueOcean\ActiveUnshippedOrdersExists;
use Illuminate\Foundation\Http\FormRequest;

class ReleaseOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'orders' => [
                'required',
                'array',
                new ActiveUnshippedOrdersExists(ActiveUnshippedOrdersExists::RELEASE_ORDERS)
            ],
        ];
    }
}
