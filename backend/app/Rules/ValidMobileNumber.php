<?php

namespace App\Rules;

use App\Services\MobileValidationService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidMobileNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        if ($value === null || $value === '') {
            return;
        }

        if (!MobileValidationService::validate($value)) {
            $fail("The {$attribute} must be a valid Turkmen mobile number (format: 9936XXXXXXXX or 9937XXXXXXXX).");
        }
    }
}
