<?php

namespace App\Http\API\Requests\WindowsKeys;

use App\Rules\WindowsKeys\OrderExists;
use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'status' => 'string',
            'vendor' => 'string|in:Microsoft,ME2',
            'key_type' => 'string|in:Home,Pro',
            'order_id' => 'integer',
            'serial_key' => 'string',
            'datetime_from' => 'date_format:Y-m-d',
            'datetime_to' => 'date_format:Y-m-d',
            'page' => 'integer',
            'per_page' => 'integer',
        ];
    }
}
