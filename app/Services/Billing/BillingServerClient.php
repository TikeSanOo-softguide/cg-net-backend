<?php

namespace App\Services\Billing;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BillingServerClient
{
    /**
     * @throws RuntimeException when the amount cannot be resolved
     */
    public function lookupBillAmount(string $accountNumber): int
    {
        $url = $this->url(config('services.billing.amount_lookup_endpoint', '/bill-details'));
        $response = Http::acceptJson()
            ->withHeaders($this->headers())
            ->timeout(15)
            ->connectTimeout(5)
            ->retry(2, 100, throw: false)
            ->get($url, [
                'account_number' => $accountNumber,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Unable to determine the FTTH bill amount from the billing server.');
        }

        $payload = $response->json() ?? [];
        $amount =
            $payload['amount'] ??
            ($payload['total_amount'] ?? ($payload['bill_amount'] ?? ($payload['data']['amount'] ?? null)));

        if (!is_numeric($amount)) {
            throw new RuntimeException('Unable to determine the FTTH bill amount for this account.');
        }

        $amount = (int) $amount;

        if ($amount < 1) {
            throw new RuntimeException('FTTH bill amount must be at least 1.');
        }

        return $amount;
    }

    /**
     * Charge / extend plan on the billing server.
     *
     * Intentionally does not retry: a timeout after the remote side succeeded
     * would otherwise double-extend the plan. Callers must reconcile unknowns.
     *
     * @return array{
     *     outcome: 'success'|'rejected'|'unknown',
     *     message?: string,
     *     billing_ref?: string|null,
     *     external_payment_ref?: string|null,
     *     http_status?: int|null,
     *     payload?: mixed,
     *     exception?: string
     * }
     */
    public function extendPlan(string $accountNumber, string $customerName, int $amount, string $paymentRef): array
    {
        $url = $this->url(config('services.billing.extend_plan_endpoint', '/extend-plan'));

        try {
            $response = Http::acceptJson()
                ->withHeaders($this->headers())
                ->timeout(15)
                ->connectTimeout(5)
                ->post($url, [
                    'account_number' => $accountNumber,
                    'customer_name' => $customerName,
                    'amount' => $amount,
                    'type' => 'ftth_plan_extension',
                    'payment_ref' => $paymentRef,
                    'idempotency_key' => $paymentRef,
                ]);
        } catch (ConnectionException $exception) {
            return [
                'outcome' => 'unknown',
                'message' => 'Billing server connection failed.',
                'exception' => $exception->getMessage(),
            ];
        }

        $payload = $response->json();

        if ($response->successful() && ($payload['success'] ?? false) === true) {
            return [
                'outcome' => 'success',
                'billing_ref' => $this->billingRef($payload),
                'external_payment_ref' => $this->paymentRef($payload, $paymentRef),
                'payload' => $payload,
            ];
        }

        // Timeouts / 5xx without a clear rejection body are treated as unknown
        // so we do not refund while the remote charge may have succeeded.
        if ($response->serverError() || $response->status() === 408 || $response->status() === 429) {
            return [
                'outcome' => 'unknown',
                'message' => $payload['message'] ?? 'Billing server did not confirm the payment.',
                'http_status' => $response->status(),
                'payload' => $payload,
            ];
        }

        return [
            'outcome' => 'rejected',
            'message' => $payload['message'] ?? 'External billing service rejected the request.',
            'http_status' => $response->status(),
            'billing_ref' => is_array($payload) ? $this->billingRef($payload) : null,
            'external_payment_ref' => is_array($payload) ? $this->paymentRef($payload) : null,
            'payload' => $payload,
        ];
    }

    /**
     * @return array{
     *     outcome: 'success'|'rejected'|'unknown'|'not_found',
     *     message?: string,
     *     billing_ref?: string|null,
     *     external_payment_ref?: string|null,
     *     payload?: mixed
     * }
     */
    public function lookupPaymentStatus(string $paymentRef): array
    {
        $url = $this->url(config('services.billing.payment_status_endpoint', '/payment-status'));

        try {
            $response = Http::acceptJson()
                ->withHeaders($this->headers())
                ->timeout(15)
                ->connectTimeout(5)
                ->retry(2, 100, throw: false)
                ->get($url, [
                    'payment_ref' => $paymentRef,
                ]);
        } catch (ConnectionException $exception) {
            return [
                'outcome' => 'unknown',
                'message' => 'Billing status lookup failed.',
                'exception' => $exception->getMessage(),
            ];
        }

        if ($response->status() === 404) {
            return [
                'outcome' => 'not_found',
                'message' => 'Payment not found on billing server.',
                'payload' => $response->json(),
            ];
        }

        if (!$response->successful()) {
            return [
                'outcome' => 'unknown',
                'message' => 'Billing status lookup returned an error.',
                'http_status' => $response->status(),
                'payload' => $response->json(),
            ];
        }

        $payload = $response->json() ?? [];
        $status = strtolower(
            (string) ($payload['status'] ?? ($payload['payment_status'] ?? ($payload['data']['status'] ?? ''))),
        );

        if (
            in_array($status, ['completed', 'success', 'paid', 'confirmed'], true) ||
            ($payload['success'] ?? false) === true
        ) {
            return [
                'outcome' => 'success',
                'billing_ref' => $this->billingRef($payload),
                'external_payment_ref' => $this->paymentRef($payload, $paymentRef),
                'payload' => $payload,
            ];
        }

        if (
            in_array($status, ['failed', 'rejected', 'cancelled', 'canceled'], true) ||
            ($payload['success'] ?? null) === false
        ) {
            return [
                'outcome' => 'rejected',
                'message' => $payload['message'] ?? 'Billing server reports payment failed.',
                'billing_ref' => $this->billingRef($payload),
                'external_payment_ref' => $this->paymentRef($payload),
                'payload' => $payload,
            ];
        }

        return [
            'outcome' => 'unknown',
            'message' => 'Billing payment status is inconclusive.',
            'payload' => $payload,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . (string) config('services.billing.api_token', ''),
            'X-Request-Source' => 'cg-net-backend',
        ];
    }

    protected function url(string $endpoint): string
    {
        $baseUrl = rtrim((string) config('services.billing.base_url', 'https://billing-server.test'), '/');
        $endpoint = ltrim($endpoint, '/');

        return $baseUrl . '/' . $endpoint;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function billingRef(array $payload): ?string
    {
        $value = $payload['billing_ref'] ?? ($payload['external_bill_ref'] ?? ($payload['reference'] ?? null));

        return $value !== null ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function paymentRef(array $payload, ?string $fallback = null): ?string
    {
        $value =
            $payload['payment_ref'] ?? ($payload['external_payment_ref'] ?? ($payload['transaction_id'] ?? $fallback));

        return $value !== null ? (string) $value : null;
    }
}
