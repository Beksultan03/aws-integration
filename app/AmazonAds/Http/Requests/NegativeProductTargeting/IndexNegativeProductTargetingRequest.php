<?php

namespace App\AmazonAds\Http\Requests\NegativeProductTargeting;

use App\AmazonAds\Http\Requests\BaseFilterRequest;

class IndexNegativeProductTargetingRequest extends BaseFilterRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'adGroupId' => 'sometimes|string',
            'entityId' => 'sometimes|string',
        ]);
    }

    /**
     * Get the filters for the query.
     *
     * @return array
     */
    public function getFilters(): array
    {
        return array_merge(parent::getFilters(), [
            'adGroupId' => $this->input('adGroupId'),
        ]);
    }
} 