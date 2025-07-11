<?php

namespace App\AmazonAds\Http\Requests\Export;

use Illuminate\Foundation\Http\FormRequest;

class ExportListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|string',
            'filters' => 'sometimes|string',
            'parentId' => 'sometimes|integer',
        ];
    }
} 