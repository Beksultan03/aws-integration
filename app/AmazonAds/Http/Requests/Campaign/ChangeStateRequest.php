<?php

namespace App\AmazonAds\Http\Requests\Campaign;

use Illuminate\Foundation\Http\FormRequest;
use App\AmazonAds\Models\Campaign;

class ChangeStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => 'required|integer',
            'state' => 'required|string|in:' . implode(',', [
                Campaign::STATE_ENABLED,
                Campaign::STATE_PAUSED,
                Campaign::STATE_PROPOSED,
                Campaign::STATE_ARCHIVED,
            ]),
            'type' => 'required|string|in:keywords,ad-group,campaign,products'
        ];
    }
}

