<?php

namespace App\Http\API\Requests\WindowsKeys;

use Illuminate\Foundation\Http\FormRequest;

class DownloadRequest extends FormRequest
{
    const array VENDORS = [
        'Microsoft',
        'ME2',
    ];

    const array KEY_TYPES = [
        'Home',
        'Pro',
    ];

    public function rules(): array
    {

        return [
            'status' => 'string',
            'vendor' => 'string|in:'.implode(',', self::VENDORS),
            'key_type' => 'string|in:'.implode(',', self::KEY_TYPES),
            'order_id' => 'integer',
            'datetime_from' => 'date_format:Y-m-d',
            'datetime_to' => 'date_format:Y-m-d',
            'page' => 'integer',
            'per_page' => 'integer',
        ];
    }
}
