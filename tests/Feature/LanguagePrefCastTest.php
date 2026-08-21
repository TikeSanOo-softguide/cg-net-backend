<?php

namespace Tests\Feature;

use App\Enums\LanguagePref;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LanguagePrefCastTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_mm_and_th_values_are_normalized(): void
    {
        $myanmar = User::factory()->create();
        $chinese = User::factory()->create();

        DB::table('users')->where('id', $myanmar->id)->update(['language_pref' => 'mm']);
        DB::table('users')->where('id', $chinese->id)->update(['language_pref' => 'th']);

        $this->assertSame(LanguagePref::My, $myanmar->fresh()->language_pref);
        $this->assertSame(LanguagePref::Zh, $chinese->fresh()->language_pref);
    }
}
