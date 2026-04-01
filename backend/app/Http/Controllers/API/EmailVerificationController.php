<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Symfony\Component\HttpFoundation\JsonResponse;

class EmailVerificationController extends Controller
{
    /**
     * Verify the email address of the user.
     *
     * @param EmailVerificationRequest $request
     * @return \App\Helpers\ApiResponse
     */
    public function verify(EmailVerificationRequest $request): JsonResponse
    {
        $request->fulfill();
        return ApiResponse::success(
            null,
            'Email verified successfully. You can now log in.',
            200
        );
    }

    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success(
                'Email already verified. You can now log in.',
                'ALREADY_VERIFIED',
                400
            );
        }

        $user->sendEmailVerificationNotification();
        return ApiResponse::success(
            null,
            'Verification email sent successfully.',
            200
        );
    }
}
