<?php

namespace App\Http\API\Requests\WindowsKeys;

use App\Http\API\Requests\BaseRequest;
use App\Models\SbSerialNumber;

class RMAErrorRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'serial_key' => ['required', 'string', sprintf('exists:%s,serial_number', SbSerialNumber::TABLE_NAME)],
            'rma_error' => ['required', 'string']
        ];
    }
}
