<?php

namespace Tests\Feature;

use App\Enums\ActivityLogName;
use App\Models\Addon;
use App\Models\Admin;
use App\Models\Network;
use App\Models\Package;
use App\Models\Speed;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
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

    public function test_deleting_a_package_logs_the_deleted_model(): void
    {
        $admin = Admin::factory()->create();
        $package = Package::factory()->create();

        $this->actingAs($admin, 'web')->delete("/packages/{$package->id}")->assertRedirect('/packages');

        $this->assertSoftDeleted('packages', ['id' => $package->id]);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'package',
            'description' => 'package_deleted',
            'subject_type' => Package::class,
            'subject_id' => $package->id,
            'causer_type' => Admin::class,
            'causer_id' => $admin->id,
            'event' => 'deleted',
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
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'addon',
            'description' => 'addon_created',
            'subject_type' => Addon::class,
            'subject_id' => $addon->id,
            'causer_type' => Admin::class,
            'causer_id' => $admin->id,
            'event' => 'created',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'addon',
            'description' => 'addon_updated',
            'subject_type' => Addon::class,
            'subject_id' => $addon->id,
            'causer_type' => Admin::class,
            'causer_id' => $admin->id,
            'event' => 'updated',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'addon',
            'description' => 'addon_deleted',
            'subject_type' => Addon::class,
            'subject_id' => $addon->id,
            'causer_type' => Admin::class,
            'causer_id' => $admin->id,
            'event' => 'deleted',
        ]);

        $this->assertSame(
            9,
            Activity::query()
                ->whereIn('log_name', [
                    ActivityLogName::Network->value,
                    ActivityLogName::Speed->value,
                    ActivityLogName::Term->value,
                ])
                ->where('causer_id', $admin->id)
                ->whereIn('description', [
                    'network_created',
                    'network_updated',
                    'network_deleted',
                    'speed_created',
                    'speed_updated',
                    'speed_deleted',
                    'term_created',
                    'term_updated',
                    'term_deleted',
                ])
                ->count(),
        );
    }

    public function test_bulk_deleting_networks_speeds_and_terms_logs_deleted_ids_once(): void
    {
        $admin = Admin::factory()->create();
        $networks = Network::factory()->count(2)->create();
        $speeds = Speed::factory()->count(2)->create();
        $terms = collect([
            Term::factory()->create(['months' => 3]),
            Term::factory()->create(['months' => 6]),
        ]);

        $this->actingAs($admin, 'web')->delete('/networks/bulk-destroy', [
            'ids' => $networks->pluck('id')->all(),
        ])->assertRedirect('/packages');

        $this->actingAs($admin, 'web')->delete('/speeds/bulk-destroy', [
            'ids' => $speeds->pluck('id')->all(),
        ])->assertRedirect('/packages');

        $this->actingAs($admin, 'web')->delete('/terms/bulk-destroy', [
            'ids' => $terms->pluck('id')->all(),
        ])->assertRedirect('/packages');

        foreach ([
            [ActivityLogName::Network, 'networks_bulk_deleted', $networks],
            [ActivityLogName::Speed, 'speeds_bulk_deleted', $speeds],
            [ActivityLogName::Term, 'terms_bulk_deleted', $terms],
        ] as [$logName, $description, $models]) {
            $activity = Activity::query()
                ->where('log_name', $logName->value)
                ->where('causer_id', $admin->id)
                ->where('description', $description)
                ->sole();

            $this->assertSame('bulk_deleted', $activity->event);
            $this->assertEqualsCanonicalizing($models->pluck('id')->all(), $activity->properties->get('ids'));
        }
    }

    public function test_bulk_deleting_packages_and_addons_logs_deleted_ids_once(): void
    {
        $admin = Admin::factory()->create();
        $packages = Package::factory()->count(2)->create();
        $addons = collect([
            Addon::query()->create([
                'name_en' => 'First add-on',
                'name_zh' => '第一個附加項',
                'name_my' => 'First add-on MM',
                'price' => 10000,
            ]),
            Addon::query()->create([
                'name_en' => 'Second add-on',
                'name_zh' => '第二個附加項',
                'name_my' => 'Second add-on MM',
                'price' => 15000,
            ]),
        ]);

        $this->actingAs($admin, 'web')->delete('/packages/bulk-destroy', [
            'ids' => $packages->pluck('id')->all(),
        ])->assertRedirect('/packages');

        $this->actingAs($admin, 'web')->delete('/addons/bulk-destroy', [
            'ids' => $addons->pluck('id')->all(),
        ])->assertRedirect('/packages');

        foreach ([
            ['package', 'packages_bulk_deleted', $packages],
            ['addon', 'addons_bulk_deleted', $addons],
        ] as [$logName, $description, $models]) {
            $activity = Activity::query()
                ->where('log_name', $logName)
                ->where('causer_id', $admin->id)
                ->where('description', $description)
                ->sole();

            $this->assertSame('bulk_deleted', $activity->event);
            $this->assertEqualsCanonicalizing($models->pluck('id')->all(), $activity->properties->get('ids'));
        }
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
