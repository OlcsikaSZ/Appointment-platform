<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PersonName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $name = trim((string) $value);

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $fail('A név 2 és 120 karakter közötti legyen.');
            return;
        }

        if (preg_match('/^[\p{L}\p{M}][\p{L}\p{M}\s.\'’\-]*$/u', $name) !== 1) {
            $fail('A név csak betűket, szóközt, pontot, kötőjelet és aposztrófot tartalmazhat.');
            return;
        }

        if (preg_match_all('/\p{L}/u', $name) < 2) {
            $fail('Adj meg egy valódi nevet legalább két betűvel.');
        }
    }
}
