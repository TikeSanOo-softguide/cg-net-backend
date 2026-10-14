<?php

namespace Database\Seeders;

use App\Services\Ledger\LedgerPoster;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        echo "Database seeder started\n";

        $this->call([
            AdminSeeder::class,
            AreaSeeder::class,
            PackageSeeder::class,
        ]);

        app(LedgerPoster::class)->ensureSystemAccounts();

        $customerSeeder = app(CustomerSeeder::class);
        $customerSeeder->setContainer(app());
        $customerSeeder->__invoke();
        $customerIds = $customerSeeder->seededUserIds;

        $this->call(OfficeSeeder::class);
        $this->call(ServiceRequestSeeder::class, false, ['userIds' => $customerIds]);
        $this->call([
            FailureReportSeeder::class,
        ]);
        $this->call(BillingSeeder::class, false, ['userIds' => $customerIds]);
        $this->call([
            NotificationTemplateSeeder::class,
            NotificationSeeder::class,
            CmsSeeder::class,
            AdminRoleSeeder::class,
            AnnouncementSeeder::class,
            TopUpCardSeeder::class,
            LedgerAccountSeeder::class,
            WalletSeeder::class,
            ChatFlowSeeder::class,
            QuickReplySeeder::class,
            ChatConversationSeeder::class,
            AppVersionSeeder::class,
            GeneralSettingSeeder::class,
            SupportHotlineSeeder::class,
        ]);
    }
}
