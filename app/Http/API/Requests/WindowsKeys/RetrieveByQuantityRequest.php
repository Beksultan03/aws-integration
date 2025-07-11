<?php
namespace App\Http\API\Requests\WindowsKeys;
use App\Http\API\Requests\BaseRequest;
use App\Rules\WindowsKeys\UserExist;

class RetrieveByQuantityRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'quantities' => json_decode($this->input('quantities'), true)
        ]);
    }
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', new UserExist()],
            'quantities' => ['required', 'array'],
        ];
    }
}
