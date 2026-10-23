<?php

namespace App\Exceptions;

use App\Enums\MessageCode;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ApiMessageException extends HttpException
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public readonly MessageCode $code,
        int $status = 422,
        public readonly array $extra = [],
        ?Throwable $previous = null,
    ) {
        $headers = [];
        if ($status === 429) {
            $retryAfter = max(1, (int) ($extra['retry_after'] ?? 1));
            $headers['Retry-After'] = (string) $retryAfter;
        }

        parent::__construct($status, $code->message(), $previous, $headers);
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return array_merge([
            'code' => $this->code->value,
            'level' => $this->code->level(),
            'message' => $this->code->message(),
        ], $this->extra);
    }

    public function toJsonResponse(): JsonResponse
    {
        return response()
            ->json($this->payload(), $this->getStatusCode())
            ->withHeaders($this->getHeaders());
    }
}
