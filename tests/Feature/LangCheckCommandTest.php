<?php

namespace Tests\Feature;

use Tests\TestCase;

class LangCheckCommandTest extends TestCase
{
    public function test_translation_files_share_the_same_keys(): void
    {
        $this->artisan('lang:check')->assertSuccessful();
    }
}
