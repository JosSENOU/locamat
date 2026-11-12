<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BeninPhoneNumber implements ValidationRule
{
    private const PREFIXES = [
        '20', '21', '22', '23', '24', '28', '29',
        '40', '41', '42', '43', '44', '45', '46', '47', '48', '49',
        '50', '51', '52', '53', '54', '55', '56', '57', '58', '59',
        '60', '61', '62', '63', '64', '65', '66', '67', '68', '69',
        '90', '91', '92', '93', '94', '95', '96', '97', '98', '99',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pattern = '/^\+229 01 (?:'.implode('|', self::PREFIXES).') \d{2} \d{2} \d{2}$/';

        if (! is_string($value) || preg_match($pattern, $value) !== 1) {
            $fail('Le numéro doit avoir un préfixe attribué au Bénin et respecter le format +229 01 XX XX XX XX.');
        }
    }
}
