<?php

namespace Tests\Unit;

use App\Support\DeviceRequestMetadata;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class DeviceRequestMetadataTest extends TestCase
{
    public function test_request_body_device_id_takes_precedence_over_the_header(): void
    {
        $request = Request::create(
            '/api/auth/login',
            'POST',
            ['device_id' => 'android:0123456789abcdef'],
            server: ['HTTP_X_DEVICE_ID' => '68f0dbcd-6e82-48f7-ab4c-17b5d1be4ad2'],
        );

        $metadata = DeviceRequestMetadata::from($request);

        $this->assertSame('android:0123456789abcdef', $metadata['device_id']);
    }

    public function test_it_falls_back_to_the_device_id_header_when_body_id_is_absent(): void
    {
        $request = Request::create(
            '/api/auth/login',
            'POST',
            server: ['HTTP_X_DEVICE_ID' => '68f0dbcd-6e82-48f7-ab4c-17b5d1be4ad2'],
        );

        $metadata = DeviceRequestMetadata::from($request);

        $this->assertSame('68f0dbcd-6e82-48f7-ab4c-17b5d1be4ad2', $metadata['device_id']);
    }
}
