<?php

namespace App\Http\API\Requests\PPC;

use Illuminate\Foundation\Http\FormRequest;

class GetSkuAsinsInfoRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'products' => 'required|array',
            'products.*' => 'int:products,products',
        ];
    }

    /**
     * @return array
     */
    public function messages()
    {
        return [
            'products.required' => 'An array of products is required.',
            'products.array' => 'Products must be an array.',
            'products.*.int' => 'Each product must be a string.',
        ];
    }
}
