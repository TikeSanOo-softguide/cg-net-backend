<?php

namespace Database\Seeders;

use App\Models\Addon;
use App\Models\Network;
use App\Models\Package;
use App\Models\Speed;
use App\Models\Term;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        // ------------------------------------------------------------
        // Networks (缅甸网 / 晨光网 / CG-家庭网)
        // ------------------------------------------------------------
        collect([
            'Myanmar Network', // 缅甸网
            'Chenguang Network', // 晨光网 (中缅一体网)
            'CG-Net', // CG-家庭网
        ])->each(
            fn(string $name) => Network::firstOrCreate([
                'name_en' => $name,
                'name_zh' => $name,
                'name_my' => $name,
            ]),
        );

        // ------------------------------------------------------------
        // Speeds (Mbps tiers)
        // ------------------------------------------------------------
        collect([20, 50, 100, 150])->each(fn(int $mbps) => Speed::firstOrCreate(['mbps' => $mbps]));

        // ------------------------------------------------------------
        // Terms (1个月 / 3个月 / 6个月 / 1年)
        // ------------------------------------------------------------
        collect([1, 3, 6, 12])->each(fn(int $months) => Term::firstOrCreate(['months' => $months]));

        // ------------------------------------------------------------
        // Addons (IPTV set-top box + CG-Net receiver)
        // ------------------------------------------------------------
        Addon::firstOrCreate(
            ['name_en' => 'IPTV Set-Top Box'], // 电视机顶盒
            ['name_zh' => 'IPTV Set-Top Box', 'name_my' => 'IPTV Set-Top Box', 'price' => 288.0, 'is_active' => true],
        );

        Addon::firstOrCreate(
            ['name_en' => 'Wireless Receiver'], // 接收器 (CG-家庭网)
            ['name_zh' => 'Wireless Receiver', 'name_my' => 'Wireless Receiver', 'price' => 350.0, 'is_active' => true],
        );

        // ------------------------------------------------------------
        // Packages pricing
        // [network name, speed mbps, term months, price, sort_order]
        // ------------------------------------------------------------
        $rows = [
            // Myanmar Network (缅甸网)
            ['Myanmar Network', 20, 1, 188, 1],
            ['Myanmar Network', 20, 3, 559, 2],
            ['Myanmar Network', 20, 6, 1099, 3],
            ['Myanmar Network', 20, 12, 1880, 4],

            ['Myanmar Network', 50, 1, 239, 5],
            ['Myanmar Network', 50, 3, 655, 6],
            ['Myanmar Network', 50, 6, 1299, 7],
            ['Myanmar Network', 50, 12, 2399, 8],

            ['Myanmar Network', 100, 1, 299, 9],
            ['Myanmar Network', 100, 3, 777, 10],
            ['Myanmar Network', 100, 6, 1499, 11],
            ['Myanmar Network', 100, 12, 2999, 12],

            ['Myanmar Network', 150, 1, 399, 13],
            ['Myanmar Network', 150, 3, 999, 14],
            ['Myanmar Network', 150, 6, 1999, 15],
            ['Myanmar Network', 150, 12, 3999, 16],

            // Chenguang Network (晨光网 - 中缅一体网)
            ['Chenguang Network', 50, 1, 259, 17],
            ['Chenguang Network', 50, 3, 655, 18],
            ['Chenguang Network', 50, 6, 1299, 19],
            ['Chenguang Network', 50, 12, 2599, 20],

            ['Chenguang Network', 100, 1, 299, 21],
            ['Chenguang Network', 100, 3, 777, 22],
            ['Chenguang Network', 100, 6, 1499, 23],
            ['Chenguang Network', 100, 12, 2999, 24],

            // CG-Net (CG-家庭网)
            ['CG-Net', 20, 1, 199, 25],
            ['CG-Net', 20, 3, 588, 26],
            ['CG-Net', 20, 6, 1099, 27],
            ['CG-Net', 20, 12, 1999, 28],
        ];

        foreach ($rows as [$networkName, $mbps, $months, $price, $sortOrder]) {
            $networkId = Network::where('name_en', $networkName)->value('id');
            $speedId = Speed::where('mbps', $mbps)->value('id');
            $termId = Term::where('months', $months)->value('id');

            $package = Package::updateOrCreate(
                [
                    'network_id' => $networkId,
                    'speed_id' => $speedId,
                    'term_id' => $termId,
                ],
                [
                    'price' => $price,
                    'installation_fee' => $this->installationFee($months, $mbps),
                    // 1-year plans bundle free install + free IPTV
                    'includes_free_iptv' => $months === 12,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                    // Flag the top 150 Mbps / 1-year tiers as recommended
                    'recommended' => $mbps === 150 || ($networkName === 'Myanmar Network' && $months === 12),
                ],
            );
        }
    }

    /**
     * Installation fee
     * 1 month: 300 (500 for 150 Mbps)
     * 3 months: 200 (400 for 150 Mbps)
     * 6 months / 1 year: free
     */
    private function installationFee(int $months, int $mbps): float
    {
        return match (true) {
            $months === 1 && $mbps === 150 => 500.0,
            $months === 1 => 300.0,
            $months === 3 && $mbps === 150 => 400.0,
            $months === 3 => 200.0,
            default => 0.0,
        };
    }
}
