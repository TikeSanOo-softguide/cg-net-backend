<?php

namespace Tests\Unit;

use App\Support\TopUpCardOffices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopUpCardOfficesTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_selection_resolves_all_active_office_codes(): void
    {
        $this->makeOffice('Alpha Office', 'Main Street', '11');
        $this->makeOffice('Beta Office', 'Second Street', '22');
        $this->makeOffice('Deleted Office', 'Gone Street', '33')->delete();

        $this->assertSame(['11', '22'], TopUpCardOffices::resolveCodes([]));
    }

    public function test_explicit_ids_resolve_only_selected_codes(): void
    {
        $first = $this->makeOffice('Alpha Office', 'Main Street', '11');
        $this->makeOffice('Beta Office', 'Second Street', '22');

        $this->assertSame(['11'], TopUpCardOffices::resolveCodes([(int) $first->id]));
    }
}
