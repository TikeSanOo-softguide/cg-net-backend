<?php

namespace Tests\Feature;

use Tests\TestCase;

class LangCheckCommandTest extends TestCase
{
    public function test_translation_files_share_the_same_keys(): void
    {
        $this->artisan('lang:check')->assertSuccessful();

        $this->assertSame('Dashboard', __('menu.dashboard'));
        $this->assertSame('ဝင်ရောက်ရန်', __('auth.sign_in', locale: 'my'));
        $this->assertSame('登录', __('auth.sign_in', locale: 'zh'));
    }
}
