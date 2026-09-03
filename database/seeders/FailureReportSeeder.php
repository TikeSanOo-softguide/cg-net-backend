<?php

namespace Database\Seeders;

use App\Enums\FailureType;
use App\Enums\ReviewStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FailureReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminId = DB::table('admins')->value('id');

        if ($adminId === null) {
            return;
        }

        $users = DB::table('users')->select('id')->get();

        if ($users->isEmpty()) {
            return;
        }

        $accounts = DB::table('broadband_accounts')
            ->select('id', 'user_id')
            ->get()
            ->keyBy('user_id');

        $seedRows = [
            [
                'user_id' => $users[0]->id,
                'broadband_account_id' => $accounts[$users[0]->id]->id ?? null,
                'failure_type' => FailureType::NoInternet->value,
                'description' => 'Customer reports no internet connection after 8:00 PM in the area.',
                'contact_name' => 'Aye Aye',
                'contact_phone' => '09770000001',
                'status' => ReviewStatus::UnderReview->value,
                'admin_id' => $adminId,
            ],
            [
                'user_id' => $users[1]->id ?? $users[0]->id,
                'broadband_account_id' => $accounts[$users[1]->id ?? $users[0]->id]->id ?? null,
                'failure_type' => FailureType::Slow->value,
                'description' => 'Line speed is slower than expected during evening hours.',
                'contact_name' => 'Moe Moe',
                'contact_phone' => '09770000002',
                'status' => ReviewStatus::Approved->value,
                'admin_id' => $adminId,
            ],
            [
                'user_id' => $users[2]->id ?? $users[0]->id,
                'broadband_account_id' => $accounts[$users[2]->id ?? $users[0]->id]->id ?? null,
                'failure_type' => FailureType::Equipment->value,
                'description' => 'Customer reports router and modem power issue at the home connection.',
                'contact_name' => 'Hla Hla',
                'contact_phone' => '09770000003',
                'status' => ReviewStatus::Rejected->value,
                'admin_id' => $adminId,
            ],
        ];

        foreach ($seedRows as $row) {
            $accountId = $row['broadband_account_id'];

            if ($accountId === null) {
                continue;
            }

            $report = DB::table('failure_reports')->where('user_id', $row['user_id'])
                ->where('contact_phone', $row['contact_phone'])
                ->first();

            if ($report) {
                DB::table('failure_reports')->where('id', $report->id)->update([
                    'broadband_account_id' => $accountId,
                    'failure_type' => $row['failure_type'],
                    'description' => $row['description'],
                    'contact_name' => $row['contact_name'],
                    'contact_phone' => $row['contact_phone'],
                    'status' => $row['status'],
                    'admin_id' => $row['admin_id'],
                    'updated_at' => now(),
                ]);

                $reportId = $report->id;
            } else {
                $reportId = DB::table('failure_reports')->insertGetId([
                    'user_id' => $row['user_id'],
                    'broadband_account_id' => $accountId,
                    'failure_type' => $row['failure_type'],
                    'description' => $row['description'],
                    'contact_name' => $row['contact_name'],
                    'contact_phone' => $row['contact_phone'],
                    'status' => $row['status'],
                    'admin_id' => $row['admin_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $existingPhotoCount = DB::table('failure_photos')
                ->where('failure_report_id', $reportId)
                ->count();

            if ($existingPhotoCount === 0) {
                DB::table('failure_photos')->insert([
                    [
                        'failure_report_id' => $reportId,
                        'image_url' => 'https://images.unsplash.com/photo-1691435828932-911a7801adfb?q=80&w=1632&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                        'label' => 'front_view',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'failure_report_id' => $reportId,
                        'image_url' => 'https://images.unsplash.com/photo-1516044734145-07ca8eef8731?q=80&w=1173&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                        'label' => 'equipment_box',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'failure_report_id' => $reportId,
                        'image_url' => 'https://images.unsplash.com/photo-1606904825846-647eb07f5be2?q=80&w=1170&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                        'label' => 'cable_line',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);
            }
        }
    }
}
