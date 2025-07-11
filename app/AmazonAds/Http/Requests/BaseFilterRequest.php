<?php

namespace App\AmazonAds\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class BaseFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filters' => 'sometimes|array',
            'searchQuery' => 'sometimes|nullable|string|max:255',
            'sort' => 'sometimes|array',
            'sort.field' => 'sometimes|string',
            'sort.direction' => 'sometimes|string|in:asc,desc,default',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1'
        ];
    }

    public function getFilters(): array
    {
        return [
            'searchQuery' => $this->input('searchQuery'),
            'user' => $this->input('user'),
            'filters' => $this->input('filters'),
            'sort' => $this->input('sort'),
            'per_page' => $this->input('per_page', 10),
            'page' => $this->input('page', 1),
        ];
    }

    public function getUser(): ?string
    {
        return $this->input('user');
    }

    public function getPagination(): array
    {
        return [
            'per_page' => $this->input('per_page', 10),
            'page' => $this->input('page', 1),
        ];
    }
} 