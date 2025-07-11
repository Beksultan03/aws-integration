<?php

namespace App\Services\EmailPasswords;

use App\BlueOcean\Exceptions\ApiException;
use App\Models\TrustedEmail;
use Illuminate\Support\Str;

class EmailPasswordService
{
    public function generatePassword($email)
    {
        $password = TrustedEmail::query()->where('email', $email)->pluck('password')->first() ?? null;

        if (!$password) {
            return throw new ApiException('Password is missing for this email.');
        }

        $split = str_split(decrypt($password), 4);

        foreach ($split as &$chunk) {
            $chunk = Str::random(6) . $chunk . Str::random(6);
        }

        return $split;
    }
}
