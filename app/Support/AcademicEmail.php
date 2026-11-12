<?php

namespace App\Support;

use Illuminate\Support\Str;

final class AcademicEmail
{
    public static function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }

    public static function isValid(string $email): bool
    {
        $email = self::normalize($email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && Str::afterLast($email, '@') === config('library.academic_email_domain');
    }
}
