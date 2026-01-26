<?php

namespace App\Services;

class MobileValidationService
{
    /**
     * Validate a mobile number
     *
     * @param string $mobile Mobile number
     *
     * @return bool Whether the mobile field is valid or not
     */
    public static function validate(string $mobile): bool
    {
        $regex = config('helper.mobile_format_verification');

        $clean_mobile = self::cleanMobile($mobile);

        return (bool) preg_match('/' . $regex .'/', $clean_mobile);
    }


    /**
     * Clean up a mobile number by removing all non-digit characters.
     *
     * @param string $mobile Mobile number
     *
     * @return string Cleaned mobile field
     */
    public static function cleanMobile(string $mobile): string
    {
        $cleaned_mobile = preg_replace('/[^\d]/', '', $mobile);

        return $cleaned_mobile;
    }

    /**
     * Format a mobile number
     *
     * Cleans up the mobile number and then formats it according to the country code and operator.
     *
     * If length equals 11, it will be formatted as follows: +XXX XX XXXXXX
     * If lengths equals 9, it will be formatted as follows: +993 XX XXXXxx
     *
     * @param string $mobile Mobile number
     *
     * @return string Formatted mobile field
     */
    public static function format(string $mobile): string
    {
        $clean_mobile = self::cleanMobile($mobile);

        if (strlen($clean_mobile) === 11) {
            return '+' . substr($clean_mobile, 0, 3) . ' ' . substr($clean_mobile, 3, 2) . ' ' . substr($clean_mobile, 5);
        } elseif (strlen($clean_mobile) == 8) {
            return '+993 ' . substr($clean_mobile, 0, 2) . ' ' . substr($clean_mobile, 2);
        }

        return $mobile;
    }

    /**
     * Returns the operator name based on the first two digits of the mobile number (return null if not valid and unknown if not found in operator list).
     *
     * @param string $mobile Mobile number
     *
     * @return string|null Operator name or null if not valid
     */
    public static function getOperator(string $mobile): ?string
    {
        if (!self::validate($mobile)) {
            return null;
        }

        $clean_mobile = self::cleanMobile($mobile);
        $operator_code = substr($clean_mobile, 0, 2);// Most likely, the first three digits will must be  (61, 62, 63, 64, 65)

        $opeerators =[
            '61' => 'TM Cell',
            '62' => 'TM Cell',
            '63' => 'Altyn Asyr',
            '64' => 'Altyn Asyr',
            '65' => 'Altyn Asyr',
        ];

        return $operators[$operator_code] ?? 'Unknown operator';
    }

    /**
     * Returns the validation message for the mobile number.
     *
     * @return string The validation message
     */
    public static function getValidationMessage(): string
    {
        return "Mobile number must match format: " . config('helper.mobile_format_verification');
    }
}