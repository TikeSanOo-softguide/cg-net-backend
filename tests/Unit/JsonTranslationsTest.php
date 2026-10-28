<?php

namespace Tests\Unit;

use App\Support\JsonTranslations;
use PHPUnit\Framework\TestCase;

class JsonTranslationsTest extends TestCase
{
    public function test_flatten_and_dotted_keys_walk_nested_trees(): void
    {
        $tree = [
            'auth' => ['sign_in' => 'Sign in'],
            'menu' => [
                'dashboard' => 'Dashboard',
                'count' => 3,
            ],
        ];

        $this->assertSame([
            'auth.sign_in' => 'Sign in',
            'menu.dashboard' => 'Dashboard',
            'menu.count' => '3',
        ], JsonTranslations::flatten($tree));

        $this->assertSame(
            ['auth.sign_in', 'menu.count', 'menu.dashboard'],
            JsonTranslations::dottedKeys($tree),
        );
    }
}
