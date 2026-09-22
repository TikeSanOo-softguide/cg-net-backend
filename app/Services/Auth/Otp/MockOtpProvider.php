<?php

namespace App\Services\Auth\Otp;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class MockOtpProvider implements OtpProviderInterface
{
    public function send(string $phone): OtpChallenge
    {
        $code = (string) random_int(100000, 999999);
        $reference = (string) Str::uuid();
        $ttl = (int) config('otp.challenge_ttl');

        Cache::store(config('otp.cache_store', 'redis'))->put(
            $this->key($reference),
            hash_hmac('sha256', $code, (string) config('app.key')),
            $ttl,
        );

        $exposeCode = config('otp.mock.expose_code') && app()->environment(['local', 'testing']);

        return new OtpChallenge(
            providerReference: $reference,
            debugCode: $exposeCode ? $code : null,
            otpHash: hash_hmac('sha256', $code, (string) config('app.key')),
        );
    }

    public function verify(string $providerReference, string $code): bool
    {
        $store = Cache::store(config('otp.cache_store', 'redis'));
        $hash = $store->get($this->key($providerReference));

        if (!is_string($hash) || !hash_equals($hash, hash_hmac('sha256', $code, (string) config('app.key')))) {
            return false;
        }

        $store->forget($this->key($providerReference));

        return true;
    }

    private function key(string $reference): string
    {
        return 'auth:otp:provider:mock:' . hash('sha256', $reference);
    }
}
