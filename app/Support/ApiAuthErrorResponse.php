<?php

namespace App\Support;

use App\Enums\MessageCode;
use App\Exceptions\ApiMessageException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ApiAuthErrorResponse
{
    public static function fromMessageException(ApiMessageException $exception): JsonResponse
    {
        return $exception->toJsonResponse();
    }

    public static function fromAuthentication(): JsonResponse
    {
        $code = MessageCode::AuthSessionExpired;

        return response()->json([
            'code' => $code->value,
            'level' => $code->level(),
            'message' => $code->message(),
        ], 401);
    }

    public static function fromValidation(ValidationException $exception, Request $request): ?JsonResponse
    {
        if (!$request->is('api/auth/*')) {
            return null;
        }

        $errors = $exception->errors();
        $code = self::codeForAuthValidation($errors);

        return response()->json([
            'code' => $code->value,
            'level' => $code->level(),
            'message' => $code->message(),
            'errors' => $errors,
        ], 422);
    }

    /** @param array<string, list<string>> $errors */
    private static function codeForAuthValidation(array $errors): MessageCode
    {
        if (isset($errors['phone'])) {
            $text = strtolower(implode(' ', $errors['phone']));

            return str_contains($text, 'required')
                ? MessageCode::AuthPhoneRequired
                : MessageCode::AuthPhoneUnsupported;
        }

        if (isset($errors['code']) || isset($errors['otp'])) {
            return MessageCode::AuthOtpFailed;
        }

        if (isset($errors['verification_token']) || isset($errors['challenge_id'])) {
            return MessageCode::AuthVerificationTokenInvalid;
        }

        if (isset($errors['password'])) {
            return MessageCode::AuthIncorrectPassword;
        }

        return MessageCode::SysUnexpected;
    }

    public static function shouldHandleAuthentication(Request $request, AuthenticationException $exception): bool
    {
        return $request->is('api/*');
    }
}
