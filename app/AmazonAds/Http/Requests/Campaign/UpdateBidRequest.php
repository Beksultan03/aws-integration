<?php

namespace App\AmazonAds\Http\Requests\Campaign;

use Illuminate\Foundation\Http\FormRequest;
class UpdateBidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => 'required|numeric',
            'value' => 'required|numeric|min:0.02',
            'type' => 'required|string|in:keywords,ad-group,campaign',
        ];
    }
}
