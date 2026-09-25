<?php

namespace Tests\Unit;

use App\Models\Agent;
use App\Support\TopUpCardAgents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopUpCardAgentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_selection_resolves_all_active_agent_codes(): void
    {
        Agent::query()->create([
            'name' => 'Alpha Office',
            'address' => 'Main Street',
            'cd' => '11',
        ]);
        Agent::query()->create([
            'name' => 'Beta Office',
            'address' => 'Second Street',
            'cd' => '22',
        ]);
        Agent::query()->create([
            'name' => 'Deleted Office',
            'address' => 'Gone Street',
            'cd' => '33',
        ])->delete();

        $this->assertSame(['11', '22'], TopUpCardAgents::resolveCodes([]));
    }

    public function test_explicit_ids_resolve_only_selected_codes(): void
    {
        $first = Agent::query()->create([
            'name' => 'Alpha Office',
            'address' => 'Main Street',
            'cd' => '11',
        ]);
        Agent::query()->create([
            'name' => 'Beta Office',
            'address' => 'Second Street',
            'cd' => '22',
        ]);

        $this->assertSame(['11'], TopUpCardAgents::resolveCodes([(int) $first->id]));
    }
}
