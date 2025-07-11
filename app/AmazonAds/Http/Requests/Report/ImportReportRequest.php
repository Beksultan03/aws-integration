<?php

namespace App\AmazonAds\Http\Requests\Report;
use Illuminate\Foundation\Http\FormRequest;

class ImportReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt,xls,xlsx|max:100000',
            'reportType' => 'string',
        ];
    }

    public function getUser(): string
    {
        return $this->input('user');
    }

} 
