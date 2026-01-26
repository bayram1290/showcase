<?php

namespace App\Http\Middleware;

use App\Services\MobileValidationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateMobile
{
    /**
     * Handle an incoming mobile request field.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $field = 'mobile'): Response
    {
        if ($request->has($field)) {
            $mobile_input = $request->input($field);

            if (! MobileValidationService::validate($mobile_input)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid mobile number format',
                    'error_code' => 'INVALID_MOBILE_NUMBER',
                    'error_message' => [
                        $field => [MobileValidationService::getValidationMessage()]
                    ]
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $request->merge([
                $field => [MobileValidationService::cleanMobile($mobile_input)]
            ]);
        }
        return $next($request);
    }


}
