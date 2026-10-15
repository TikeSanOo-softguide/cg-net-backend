<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Admin;
use App\Models\Network;
use App\Models\Package;
use App\Models\Speed;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackagePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_package(): void
    {
        $admin = Admin::factory()->create();
        $network = Network::factory()->create();
        $speed = Speed::factory()->create();
        $term = Term::factory()->create();

        $this->actingAs($admin, 'web')
            ->post('/packages', $this->payload($network->id, $speed->id, $term->id))
            ->assertRedirect('/packages');

        $this->assertDatabaseHas('packages', [
            'network_id' => $network->id,
            'speed_id' => $speed->id,
            'term_id' => $term->id,
            'price' => 25000,
            'installation_fee' => 5000,
            'includes_free_iptv' => true,
            'is_active' => true,
            'recommended' => false,
            'sort_order' => 10,
        ]);
    }

    public function test_duplicate_network_speed_and_term_combinations_are_rejected(): void
    {
        $admin = Admin::factory()->create();
        $network = Network::factory()->create();
        $speed = Speed::factory()->create();
        $term = Term::factory()->create();

        Package::query()->create($this->payload($network->id, $speed->id, $term->id));

        $this->actingAs($admin, 'web')
            ->from('/packages')
            ->post('/packages', $this->payload($network->id, $speed->id, $term->id))
            ->assertRedirect('/packages')
            ->assertSessionHasErrors(['network_id']);

        $this->assertDatabaseCount('packages', 1);
    }

    public function test_admin_can_create_update_and_delete_networks_terms_speeds_and_addons(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')
            ->post('/networks', [
                'name_en' => 'FiberNet',
                'name_zh' => '光纖網',
                'name_my' => 'FiberNet MM',
            ])
            ->assertRedirect('/packages');

        $network = Network::query()->firstOrFail();
        $this->assertDatabaseHas('networks', ['id' => $network->id, 'name_en' => 'FiberNet']);

        $this->actingAs($admin, 'web')
            ->put("/networks/{$network->id}", [
                'name_en' => 'Updated FiberNet',
                'name_zh' => '更新光纖網',
                'name_my' => 'Updated FiberNet MM',
            ])
            ->assertRedirect('/packages');

        $this->assertDatabaseHas('networks', ['id' => $network->id, 'name_en' => 'Updated FiberNet']);

        $this->actingAs($admin, 'web')
            ->post('/speeds', ['mbps' => 100])
            ->assertRedirect('/packages');

        $speed = Speed::query()->firstOrFail();
        $this->assertDatabaseHas('speeds', ['id' => $speed->id, 'mbps' => 100]);

        $this->actingAs($admin, 'web')
            ->put("/speeds/{$speed->id}", ['mbps' => 200])
            ->assertRedirect('/packages');

        $this->assertDatabaseHas('speeds', ['id' => $speed->id, 'mbps' => 200]);

        $this->actingAs($admin, 'web')
            ->post('/terms', ['months' => 12])
            ->assertRedirect('/packages');

        $term = Term::query()->firstOrFail();
        $this->assertDatabaseHas('terms', ['id' => $term->id, 'months' => 12]);

        $this->actingAs($admin, 'web')
            ->put("/terms/{$term->id}", ['months' => 24])
            ->assertRedirect('/packages');

        $this->assertDatabaseHas('terms', ['id' => $term->id, 'months' => 24]);

        $this->actingAs($admin, 'web')
            ->post('/addons', [
                'name_en' => 'Wifi Booster',
                'name_zh' => 'WiFi 增強器',
                'name_my' => 'Wifi Booster MM',
                'price' => 15000,
            ])
            ->assertRedirect('/packages');

        $addon = Addon::query()->firstOrFail();
        $this->assertDatabaseHas('addons', ['id' => $addon->id, 'name_en' => 'Wifi Booster', 'price' => 15000]);

        $this->actingAs($admin, 'web')
            ->put("/addons/{$addon->id}", [
                'name_en' => 'Updated Wifi Booster',
                'name_zh' => '更新 WiFi 增強器',
                'name_my' => 'Updated Wifi Booster MM',
                'price' => 20000,
            ])
            ->assertRedirect('/packages');

        $this->assertDatabaseHas('addons', ['id' => $addon->id, 'name_en' => 'Updated Wifi Booster', 'price' => 20000]);

        $this->actingAs($admin, 'web')->delete("/networks/{$network->id}")->assertRedirect('/packages');
        $this->actingAs($admin, 'web')->delete("/speeds/{$speed->id}")->assertRedirect('/packages');
        $this->actingAs($admin, 'web')->delete("/terms/{$term->id}")->assertRedirect('/packages');
        $this->actingAs($admin, 'web')->delete("/addons/{$addon->id}")->assertRedirect('/packages');

        $this->assertSoftDeleted('networks', ['id' => $network->id]);
        $this->assertSoftDeleted('speeds', ['id' => $speed->id]);
        $this->assertSoftDeleted('terms', ['id' => $term->id]);
        $this->assertSoftDeleted('addons', ['id' => $addon->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $networkId, int $speedId, int $termId): array
    {
        return [
            'network_id' => $networkId,
            'speed_id' => $speedId,
            'term_id' => $termId,
            'price' => 25000,
            'installation_fee' => 5000,
            'includes_free_iptv' => true,
            'is_active' => true,
            'recommended' => false,
            'sort_order' => 10,
        ];
    }
}
