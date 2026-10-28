<?php

namespace App\Support;

use App\Enums\MessageCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ApiFrameworkErrorResponse
{
    public static function decorate(Response $response, Throwable $exception, Request $request): ?JsonResponse
    {
        if (!$request->is('api/*')) {
            return null;
        }

        $payload = self::payload($response);

        if (self::alreadyCoded($payload)) {
            return null;
        }

        if ($exception instanceof ValidationException) {
            return ApiAuthErrorResponse::fromValidation($exception, $request)
                ?? self::json(MessageCode::SysValidation, 422, $response, [
                    'errors' => $exception->errors(),
                ]);
        }

        if ($response->getStatusCode() === 429 && $request->is('api/redeem/check-serial-no')) {
            $retryAfter = max(1, (int) $response->headers->get('Retry-After', 1800));
            $json = self::json(MessageCode::TopupSerialThrottled, 429, $response, [
                'retry_after' => $retryAfter,
            ]);
            $json->headers->set('Retry-After', (string) $retryAfter);

            return $json;
        }

        $status = $response->getStatusCode();
        $code = match ($status) {
            401 => MessageCode::AuthSessionExpired,
            403 => MessageCode::SysForbidden,
            404 => MessageCode::SysNotFound,
            429 => MessageCode::AuthTooManyRequests,
            500, 503 => MessageCode::SysUnexpected,
            default => null,
        };

        if ($code === null) {
            return null;
        }

        $extra = [];
        $message = $payload['message'] ?? null;

        if (is_string($message) && $message !== '' && !self::isGeneric($message)) {
            $extra['message'] = $message;
        }

        if ($status === 429) {
            $retryAfter = $payload['retry_after'] ?? $response->headers->get('Retry-After');

            if (is_numeric($retryAfter)) {
                $extra['retry_after'] = (int) $retryAfter;
            }
        }

        if (isset($payload['errors']) && is_array($payload['errors'])) {
            $extra['errors'] = $payload['errors'];
        }

        return self::json($code, $status, $response, $extra);
    }

    /** @param array<string, mixed> $extra */
    private static function json(MessageCode $code, int $status, Response $response, array $extra = []): JsonResponse
    {
        $json = response()->json(ApiMessage::attach($code, $extra), $status);
        $retryAfter = $response->headers->get('Retry-After');

        if ($retryAfter !== null) {
            $json->headers->set('Retry-After', $retryAfter);
        }

        return $json;
    }

    /** @return array<string, mixed> */
    private static function payload(Response $response): array
    {
        $decoded = json_decode($response->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $payload */
    private static function alreadyCoded(array $payload): bool
    {
        foreach (['code', 'message_code'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && str_starts_with($value, 'MSG-')) {
                return true;
            }
        }

        return false;
    }

    private static function isGeneric(string $message): bool
    {
        return in_array($message, [
            'Unauthenticated.',
            'Forbidden',
            'Not Found',
            'Server Error',
            'Too Many Requests',
            'Too Many Attempts.',
            'This action is unauthorized.',
        ], true);
    }
}
