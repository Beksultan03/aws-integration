<?php

namespace App\Http\API\Requests\WindowsKeys;
use App\Http\API\Requests\BaseRequest;
use App\Rules\WindowsKeys\UserExist;

class UploadRequest extends BaseRequest
{
    const ALLOWED_FILE_TYPES = ['csv', 'xlsx', 'xls', 'ods'];

    public function rules(): array
    {
        return [
            'file' => 'required|mimes:'. implode(',', self::ALLOWED_FILE_TYPES),
            'user_id' => ['required', 'integer', new UserExist()],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'The file is required.',
            'file.Enum' => 'The selected file is invalid. Allowed mimes are: ' . implode(',', self::ALLOWED_FILE_TYPES),
        ];
    }

}
