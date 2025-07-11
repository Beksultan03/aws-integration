<?php

namespace App\Rules\WindowsKeys;

use App\Models\SbUser;
use Illuminate\Contracts\Validation\Rule;

class UserExist implements Rule
{

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        $existsInUsers = SbUser::query()->where('id', $value)->where('is_active', 1)->exists();
        return $existsInUsers;
    }

    /**
     * Get the validation error message for the rule.
     *
     * @param string $attribute
     * @return string
     * @throws \Exception
     */
    public function message(): string
    {
        return 'The selected user ID does not exist in tbl_sb_users.';
    }
}
