<?php

namespace Tests\Feature;

use App\Enums\BillPaymentNotificationEvent;
use App\Enums\NotificationTemplateType;
use App\Models\Admin;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Notifications\FtthBillPaymentStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NotificationTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\NotificationTemplateSeeder::class);
    }

    public function test_payment_templates_are_seeded_idempotently_without_overwriting_edits(): void
    {
        $this->assertDatabaseHas('notification_templates', [
            'type' => NotificationTemplateType::BillAlert->value,
        ]);

        $this->seed(\Database\Seeders\NotificationTemplateSeeder::class);

        $template = NotificationTemplate::query()
            ->where('type', NotificationTemplateType::FtthBillPaymentCompleted->value)
            ->firstOrFail();
        $template->update(['title_en' => 'Custom payment title']);

        $this->seed(\Database\Seeders\NotificationTemplateSeeder::class);

        $this->assertSame(
            'Custom payment title',
            $template->fresh()->title_en,
        );
        $this->assertDatabaseHas('notification_templates', [
            'type' => NotificationTemplateType::FtthBillPaymentProcessing->value,
        ]);
        $this->assertDatabaseHas('notification_templates', [
            'type' => NotificationTemplateType::FtthBillPaymentRefunded->value,
        ]);
    }

    public function test_admin_can_update_the_bill_alert_template_in_all_supported_languages(): void
    {
        $admin = Admin::factory()->create();
        $permission = Permission::findOrCreate('notifications.view', 'web');
        $updatePermission = Permission::findOrCreate('notifications.update', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role = Role::findOrCreate('Notification Template Editor', 'web');
        $role->givePermissionTo([$permission, $updatePermission]);
        $admin->assignRole($role);
        $template = NotificationTemplate::query()
            ->where('type', NotificationTemplateType::BillAlert->value)
            ->firstOrFail();
        $this->assertSame(NotificationTemplateType::BillAlert, $template->type);

        $this->actingAs($admin, 'web')
            ->get('/notifications/templates')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Notification/Templates/Index')
                    ->where('templates.0.type', 'bill_alert'),
            );

        $this->actingAs($admin, 'web')
            ->put("/notifications/templates/{$template->id}", [
                'title_en' => 'Bill due',
                'title_my' => 'ဘေလ်ကျသင့်ပြီ',
                'title_zh' => '账单到期',
                'description_en' => 'Pay account {account_number} by {due_date}.',
                'description_my' => '{account_number} အကောင့်ကို {due_date} မတိုင်မီ ပေးချေပါ။',
                'description_zh' => '请在 {due_date} 前支付账户 {account_number}。',
            ])
            ->assertRedirect(route('notifications.templates.index'))
            ->assertSessionHas('success', 'notification.template.updated');

        $this->assertSame(
            'Pay account 1234 by tomorrow.',
            $template->fresh()->render('en', ['account_number' => '1234', 'due_date' => 'tomorrow'])['description'],
        );
    }

    public function test_template_view_and_update_permissions_are_enforced_through_roles(): void
    {
        $viewPermission = Permission::findOrCreate('notifications.view', 'web');
        $updatePermission = Permission::findOrCreate('notifications.update', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $viewRole = Role::findOrCreate('Notification Template Viewer', 'web');
        $viewRole->syncPermissions([$viewPermission]);
        $viewer = Admin::factory()->create();
        $viewer->assignRole($viewRole);

        $this->actingAs($viewer, 'web')->get('/notifications/templates')->assertOk()->assertInertia(
            fn(Assert $page) => $page
                ->component('Notification/Templates/Index')
                ->where('templates.0.type', 'bill_alert')
                ->where('auth.permissions', [$viewPermission->name]),
        );

        $template = NotificationTemplate::query()
            ->where('type', NotificationTemplateType::BillAlert->value)
            ->firstOrFail();
        $this->actingAs($viewer, 'web')
            ->put("/notifications/templates/{$template->id}", [
                'title_en' => 'Bill due',
                'title_my' => 'ဘေလ်ကျသင့်ပြီ',
                'title_zh' => '账单到期',
                'description_en' => 'Pay account {account_number} by {due_date}.',
                'description_my' => '{account_number} အကောင့်ကို {due_date} မတိုင်မီ ပေးချေပါ။',
                'description_zh' => '请在 {due_date} 前支付账户 {account_number}。',
            ])
            ->assertForbidden();

        $updateRole = Role::findOrCreate('Notification Template Updater', 'web');
        $updateRole->syncPermissions([$updatePermission]);
        $updater = Admin::factory()->create();
        $updater->assignRole($updateRole);

        $this->actingAs($updater, 'web')->get('/notifications/templates')->assertOk()->assertInertia(
            fn(Assert $page) => $page
                ->component('Notification/Templates/Index')
                ->where('templates.0.type', 'bill_alert')
                ->where('auth.permissions', [$updatePermission->name, $viewPermission->name]),
        );

        $this->actingAs($updater, 'web')
            ->put("/notifications/templates/{$template->id}", [
                'title_en' => 'Updated bill due',
                'title_my' => 'ဘေလ်ကျသင့်ပြီ',
                'title_zh' => '账单到期',
                'description_en' => 'Pay account {account_number} by {due_date}.',
                'description_my' => '{account_number} အကောင့်ကို {due_date} မတိုင်မီ ပေးချေပါ။',
                'description_zh' => '请在 {due_date} 前支付账户 {account_number}。',
            ])
            ->assertRedirect(route('notifications.templates.index'));
    }

    public function test_bill_payment_status_notifications_render_their_language_template(): void
    {
        $user = User::factory()->create(['lang' => 'zh']);
        $template = NotificationTemplate::query()
            ->where('type', NotificationTemplateType::FtthBillPaymentCompleted->value)
            ->firstOrFail();
        $template->update([
            'title_zh' => '支付完成',
            'description_zh' => '账户 {account_number} 已支付 {amount}。',
        ]);

        $notification = new FtthBillPaymentStatusNotification(
            event: BillPaymentNotificationEvent::Completed,
            transactionNo: 'TX-100',
            amount: 1200,
            accountNumber: 'CG1234',
        );

        $this->assertSame(
            [
                'title' => '支付完成',
                'description' => '账户 CG1234 已支付 1,200 Points。',
            ],
            $notification->content($user),
        );
    }

    public function test_each_bill_payment_event_uses_its_matching_template(): void
    {
        $user = User::factory()->create(['lang' => 'en']);

        foreach ([
            [BillPaymentNotificationEvent::Processing, NotificationTemplateType::FtthBillPaymentProcessing],
            [BillPaymentNotificationEvent::Completed, NotificationTemplateType::FtthBillPaymentCompleted],
            [BillPaymentNotificationEvent::Refunded, NotificationTemplateType::FtthBillPaymentRefunded],
        ] as [$event, $templateType]) {
            $notification = new FtthBillPaymentStatusNotification(
                event: $event,
                transactionNo: 'TX-100',
                amount: 1200,
                accountNumber: 'CG1234',
            );

            $this->assertSame(
                NotificationTemplate::query()->where('type', $templateType->value)->firstOrFail()->title_en,
                $notification->content($user)['title'],
            );
        }
    }

    public function test_mobile_inbox_uses_saved_user_language_and_current_template_content(): void
    {
        $user = User::factory()->create(['lang' => 'zh']);
        $notification = Notification::factory()->create(['user_id' => $user->id]);
        $template = $notification->templateable;
        $template->update([
            'title_zh' => '账单即将到期',
            'description_zh' => '账户 {account_number} 的账单将于 {due_date} 到期。',
        ]);
        $notification->update([
            'template_data' => ['account_number' => '1234', 'due_date' => '2026-10-15'],
        ]);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.title', '账单即将到期')
            ->assertJsonPath('data.0.body', '账户 1234 的账单将于 2026-10-15 到期。');
    }
}
