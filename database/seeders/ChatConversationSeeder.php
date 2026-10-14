<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Factories\Support\MyanmarFake;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ChatConversationSeeder extends Seeder
{
    public function run(): void
    {
        echo "Chat Converstion seeder started\n";

        $admin = DB::table('admins')->first();

        if (! $admin) {
            $adminId = DB::table('admins')->insertGetId([
                'username' => 'John Doe',
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $adminId = $admin->id;
        }

        $customers = [
            [
                'name' => 'Sarah Miller',
                'phone' => MyanmarFake::phone('mm'),
            ],
            [
                'name' => 'James Wilson',
                'phone' => MyanmarFake::phone('mm'),
            ],
            [
                'name' => 'Emily Davis',
                'phone' => MyanmarFake::phone('mm'),
            ],
            [
                'name' => 'Michael Brown',
                'phone' => MyanmarFake::phone('mm'),
            ],
            [
                'name' => 'Jessica Taylor',
                'phone' => MyanmarFake::phone('mm'),
            ],
            [
                'name' => 'David Thomas',
                'phone' => MyanmarFake::phone('mm'),
            ],
            [
                'name' => 'Laura Wilson',
                'phone' => MyanmarFake::phone('mm'),
            ],
            [
                'name' => 'Robert Anderson',
                'phone' => MyanmarFake::phone('mm'),
            ],
            [
                'name' => 'Patricia Martinez',
                'phone' => MyanmarFake::phone('mm'),
            ],
            [
                'name' => 'Daniel Jackson',
                'phone' => MyanmarFake::phone('mm'),
            ],
        ];

        $userIds = [];

        foreach ($customers as $customer) {
            $user = User::firstOrCreate(
                ['phone' => $customer['phone']],
                [
                    'name' => $customer['name'],
                    'password' => Hash::make('password'),
                ]
            );

            $userIds[$customer['name']] = $user->id;
        }

        $conversation1 = DB::table('chat_conversations')->insertGetId([
            'user_id' => $userIds['Sarah Miller'],
            'agent_id' => $adminId,
            'current_step_id' => null,
            'status' => 'open',
            'created_at' => now()->subMinutes(35),
            'updated_at' => now()->subMinutes(2),
        ]);

        $conversation2 = DB::table('chat_conversations')->insertGetId([
            'user_id' => $userIds['James Wilson'],
            'agent_id' => $adminId,
            'current_step_id' => null,
            'status' => 'open',
            'created_at' => now()->subHour(),
            'updated_at' => now()->subMinutes(10),
        ]);

        $conversation3 = DB::table('chat_conversations')->insertGetId([
            'user_id' => $userIds['Emily Davis'],
            'agent_id' => null,
            'current_step_id' => null,
            'status' => 'waiting_agent',
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subMinutes(30),
        ]);

        $conversation4 = DB::table('chat_conversations')->insertGetId([
            'user_id' => $userIds['Michael Brown'],
            'agent_id' => $adminId,
            'current_step_id' => null,
            'status' => 'open',
            'created_at' => now()->subHours(3),
            'updated_at' => now()->subHour(),
        ]);

        $conversation5 = DB::table('chat_conversations')->insertGetId([
            'user_id' => $userIds['Jessica Taylor'],
            'agent_id' => $adminId,
            'current_step_id' => null,
            'status' => 'closed',
            'created_at' => now()->subHours(5),
            'updated_at' => now()->subHours(4),
        ]);


        $this->message(
            $conversation1,
            'customer',
            "I'm still not getting internet connection. Can you help?",
            now()->subMinutes(35),
            false
        );

        $this->message(
            $conversation1,
            'agent',
            "Hi Sarah! I'm sorry to hear that you're having trouble with your internet connection. I'd be happy to help you with that. Could you please tell me when this started and if you're seeing any error messages?",
            now()->subMinutes(33),
            true
        );

        $this->message(
            $conversation1,
            'system',
            "I've checked your account and can see there was a brief outage in your area about 30 minutes ago. Your service should be back online now. Could you please try restarting your modem and router?",
            now()->subMinutes(31),
            true
        );

        $this->message(
            $conversation1,
            'customer',
            "Thanks for the update. I've restarted my router and it's still not working. Could you check if there are any further outages in my area?",
            now()->subMinutes(28),
            false
        );

        $this->message(
            $conversation1,
            'system',
            "I've checked again and there are no current outages in your area. Please wait a few more minutes and try connecting again. If the issue persists, I can help you troubleshoot further.",
            now()->subMinutes(26),
            true
        );

        $this->message(
            $conversation1,
            'system',
            'Agent John Doe assigned this conversation to you.',
            now()->subMinutes(25),
            true
        );

        $this->message(
            $conversation1,
            'agent',
            "Got it! I'll keep an eye on this and let you know if I see any updates.",
            now()->subMinutes(23),
            true
        );

        $this->message(
            $conversation1,
            'customer',
            "Thank you so much! I'll try again now.",
            now()->subMinutes(20),
            false
        );

        $this->message(
            $conversation2,
            'customer',
            'Thanks for the help! My payment has been confirmed.',
            now()->subMinutes(10),
            false
        );

        $this->message(
            $conversation3,
            'customer',
            'Can you tell me when my package will arrive?',
            now()->subMinutes(30),
            false
        );

        $this->message(
            $conversation4,
            'customer',
            'The router keeps restarting every few minutes.',
            now()->subHour(),
            false
        );

        $this->message(
            $conversation5,
            'customer',
            'I need to update my account information.',
            now()->subHours(4),
            true
        );

    }

    private function message(
        int $conversationId,
        string $senderType,
        string $message,
        $createdAt,
        bool $isRead
    ): void {
        DB::table('chat_messages')->insert([
            'conversation_id' => $conversationId,
            'sender_type' => $senderType,
            'message' => $message,
            'attachment_path' => null,
            'option_id' => null,
            'is_read' => $isRead,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
