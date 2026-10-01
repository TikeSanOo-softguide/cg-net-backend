<?php

namespace Tests\Unit;

use App\Support\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_put_and_get_boolean_setting(): void
    {
        $this->assertTrue(AppSetting::boolean(AppSetting::LEDGER_HEALTH_DAILY_SCAN_ENABLED, true));

        AppSetting::put(AppSetting::LEDGER_HEALTH_DAILY_SCAN_ENABLED, false);

        $this->assertFalse(AppSetting::boolean(AppSetting::LEDGER_HEALTH_DAILY_SCAN_ENABLED, true));
        $this->assertFalse(AppSetting::get(AppSetting::LEDGER_HEALTH_DAILY_SCAN_ENABLED, true));

        AppSetting::put(AppSetting::LEDGER_HEALTH_DAILY_SCAN_ENABLED, true);

        $this->assertTrue(AppSetting::boolean(AppSetting::LEDGER_HEALTH_DAILY_SCAN_ENABLED, false));
    }
}
