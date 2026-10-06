<?php

namespace Tests\Unit;

use App\Models\Office;
use App\Support\TopUpCardOffices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopUpCardOfficesTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_selection_resolves_all_active_office_codes(): void
    {
        Office::query()->create([
            'name' => 'Alpha Office',
            'address' => 'Main Street',
            'cd' => '11',
        ]);
        Office::query()->create([
            'name' => 'Beta Office',
            'address' => 'Second Street',
            'cd' => '22',
        ]);
        Office::query()->create([
            'name' => 'Deleted Office',
            'address' => 'Gone Street',
            'cd' => '33',
        ])->delete();

        $this->assertSame(['11', '22'], TopUpCardOffices::resolveCodes([]));
    }

    public function test_explicit_ids_resolve_only_selected_codes(): void
    {
        $first = Office::query()->create([
            'name' => 'Alpha Office',
            'address' => 'Main Street',
            'cd' => '11',
        ]);
        Office::query()->create([
            'name' => 'Beta Office',
            'address' => 'Second Street',
            'cd' => '22',
        ]);

        $this->assertSame(['11'], TopUpCardOffices::resolveCodes([(int) $first->id]));
    }
}
