<?php

namespace Tests\Unit;

use App\Support\TopUpCardPin;
use Tests\TestCase;

class TopUpCardPinTest extends TestCase
{
    public function test_hash_is_stable_hmac_sha256_hex_of_pin_only(): void
    {
        $pin = '1234567890123456';

        $hash = TopUpCardPin::hash($pin);

        $this->assertSame(64, strlen($hash));
        $this->assertTrue(ctype_xdigit($hash));
        $this->assertSame($hash, TopUpCardPin::hash($pin));
        $this->assertTrue(TopUpCardPin::check($pin, $hash));
        $this->assertFalse(TopUpCardPin::check('9999999999999999', $hash));
    }
}
