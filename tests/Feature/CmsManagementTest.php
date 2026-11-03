<?php

namespace Tests\Feature;

use App\Enums\AnnouncementType;
use App\Enums\NotificationCategory;
use App\Enums\NewsStatus;
use App\Models\Admin;
use App\Models\Announcement;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Contact;
use App\Models\News;
use App\Models\Notification as UserNotification;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\User;
use App\Notifications\AdminPushNotification;
use App\Notifications\CampaignNotification;
use App\Services\Notification\PushNotificationService;
use App\Support\CmsPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CmsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        foreach (CmsPermissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_super_admin_can_be_given_cms_permissions_after_cache_reset(): void
    {
        $admin = Admin::factory()->create(['username' => 'admin']);

        $permissions = collect(CmsPermissions::all())->map(
            fn(string $name) => Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]),
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin->givePermissionTo($permissions->all());

        $this->assertTrue($admin->hasPermissionTo('cms.view', 'web'));
        $this->assertTrue($admin->hasPermissionTo('cms.create', 'web'));
    }

    public function test_guests_cannot_view_cms_pages(): void
    {
        $this->get('/cms/promotions')->assertRedirect('/login');
        $this->get('/cms/news')->assertRedirect('/login');
    }

    public function test_admins_can_list_and_filter_promotions(): void
    {
        $admin = Admin::factory()->create();
        Promotion::factory()->create(['title_en' => 'Monsoon offer']);
        Promotion::factory()->create(['title_en' => 'Chinese banner', 'is_active' => false]);

        $this->actingAs($admin, 'web')
            ->get('/cms/promotions?search=Monsoon&status=active')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Cms/promotion/Index')
                    ->has('items.data', 1)
                    ->where('items.data.0.title_en', 'Monsoon offer'),
            );
    }

    public function test_admins_can_create_update_and_delete_a_promotion(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')
            ->post('/cms/promotions', [
                'title_en' => 'Summer promo',
                'title_my' => 'Summer promo',
                'title_zh' => 'Summer promo',
                'description_en' => 'Save this month',
                'description_my' => 'Save this month',
                'description_zh' => 'Save this month',
                'slug' => 'summer-promo',
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-31',
                'is_active' => '1',
                'image' => $this->fakeImage('promo.jpg'),
            ])
            ->assertRedirect('/cms/promotions');

        $promotion = Promotion::query()->firstOrFail();
        $this->assertSame('Summer promo', $promotion->title_en);
        Storage::disk('public')->assertExists($promotion->image_path);

        $this->actingAs($admin, 'web')
            ->put('/cms/promotions/' . $promotion->id, [
                'title_en' => 'Summer promo updated',
                'title_my' => 'Summer promo updated',
                'title_zh' => 'Summer promo updated',
                'description_en' => 'Save this month',
                'description_my' => 'Save this month',
                'description_zh' => 'Save this month',
                'slug' => 'summer-promo',
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-31',
                'is_active' => '0',
            ])
            ->assertRedirect('/cms/promotions');

        $this->assertSame('Summer promo updated', $promotion->fresh()->title_en);
        $this->assertFalse($promotion->fresh()->is_active);

        $this->actingAs($admin, 'web')
            ->delete('/cms/promotions/' . $promotion->id)
            ->assertRedirect('/cms/promotions');

        $this->assertSoftDeleted($promotion);
    }

    public function test_existing_banners_are_managed_under_cms(): void
    {
        $admin = Admin::factory()->create();
        Banner::factory()->create();

        $this->actingAs($admin, 'web')
            ->get('/cms/banners')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page->component('Cms/banner/Index')->has('items.data', 1));

        $this->actingAs($admin, 'web')->get('/banners')->assertNotFound();
    }

    public function test_banner_update_cannot_exceed_a_limited_type_capacity(): void
    {
        $admin = Admin::factory()->create();
        Banner::factory()->create(['type' => 'app_entry']);
        $banner = Banner::factory()->create(['type' => 'web_background']);

        $this->actingAs($admin, 'web')
            ->from('/cms/banners')
            ->put('/cms/banners/' . $banner->id, [
                'type' => 'app_entry',
                'sort_order' => $banner->sort_order,
                'start_date' => null,
                'end_date' => null,
                'is_active' => true,
            ])
            ->assertRedirect('/cms/banners')
            ->assertSessionHasErrors('type');

        $this->assertSame('web_background', $banner->fresh()->type->value);
    }

    public function test_banner_update_keeps_its_slot_when_type_is_at_capacity(): void
    {
        $admin = Admin::factory()->create();
        $banner = Banner::factory()->create(['type' => 'app_entry']);

        $this->actingAs($admin, 'web')
            ->put('/cms/banners/' . $banner->id, [
                'type' => 'app_entry',
                'sort_order' => $banner->sort_order,
                'start_date' => null,
                'end_date' => null,
                'is_active' => true,
            ])
            ->assertRedirect('/cms/banners');

        $this->assertSame('app_entry', $banner->fresh()->type->value);
    }

    public function test_news_can_be_created_with_category(): void
    {
        $admin = Admin::factory()->create();
        $category = Category::factory()->create(['name_en' => 'Offers', 'slug' => 'offers']);
        $this->actingAs($admin, 'web')
            ->post('/cms/news', [
                'category_id' => $category->id,
                'title_en' => 'Coverage update',
                'title_my' => 'Coverage update',
                'title_zh' => 'Coverage update',
                'description_en' => 'Fiber is now live.',
                'description_my' => 'Fiber is now live.',
                'description_zh' => 'Fiber is now live.',
                'slug' => 'coverage-update',
                'status' => NewsStatus::Published->value,
                'image' => $this->fakeImage('news.jpg'),
            ])
            ->assertRedirect('/cms/news');

        $news = News::query()->firstOrFail();
        $this->assertSame($category->id, $news->category_id);
        Storage::disk('public')->assertExists($news->image_path);
    }

    public function test_services_can_be_created_updated_and_deleted_from_cms(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')
            ->get('/cms/services')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page->component('Cms/service/Index'));

        $this->actingAs($admin, 'web')
            ->post('/cms/services', [
                'title_en' => 'Business Internet',
                'title_zh' => '商业宽带',
                'title_my' => 'စီးပွားရေးအင်တာနက်',
                'description_en' => 'Reliable business connectivity.',
                'description_zh' => '可靠的商业连接服务。',
                'description_my' => 'စစ်မှန်သောစီးပွားရေးကွန်ယက်ဆက်သွယ်မှု။',
                'slug' => 'business-internet',
                'status' => 'published',
                'image' => $this->fakeImage('service.jpg'),
            ])
            ->assertRedirect('/cms/services');

        $service = Service::query()->firstOrFail();
        $this->assertSame('Business Internet', $service->title_en);
        Storage::disk('public')->assertExists($service->image_path);

        $this->actingAs($admin, 'web')
            ->put('/cms/services/' . $service->id, [
                'title_en' => 'Business Internet Plus',
                'title_zh' => '商业宽带增强版',
                'title_my' => 'စီးပွားရေးအင်တာနက်အတိုး',
                'description_en' => 'Updated business connectivity.',
                'description_zh' => '更新后的商业连接服务。',
                'description_my' => 'ပြင်ဆင်ထားသောစီးပွားရေးကွန်ယက်ဆက်သွယ်မှု။',
                'slug' => 'business-internet-plus',
                'status' => 'archived',
            ])
            ->assertRedirect('/cms/services');

        $this->assertSame('Business Internet Plus', $service->fresh()->title_en);
        $this->assertSame('archived', $service->fresh()->status->value);

        $this->actingAs($admin, 'web')
            ->delete('/cms/services/' . $service->id)
            ->assertRedirect('/cms/services');

        $this->assertSoftDeleted($service);
    }

    public function test_category_with_news_cannot_be_deleted(): void
    {
        $admin = Admin::factory()->create();
        $category = Category::factory()->create();
        News::factory()->create(['category_id' => $category->id]);

        $this->actingAs($admin, 'web')
            ->from('/cms/categories')
            ->delete('/cms/categories/' . $category->id)
            ->assertRedirect('/cms/categories')
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'deleted_at' => null]);
    }

    public function test_category_names_must_be_unique_among_non_deleted_categories(): void
    {
        $admin = Admin::factory()->create();
        $existing = Category::factory()->create([
            'name_en' => 'Unique English',
            'name_zh' => '唯一中文',
            'name_my' => 'ထူးခြားသောမြန်မာ',
        ]);

        foreach (['name_en', 'name_zh', 'name_my'] as $field) {
            $values = [
                'name_en' => 'Another English',
                'name_zh' => '另一个中文',
                'name_my' => 'အခြားမြန်မာ',
                'slug' => 'another-' . $field,
            ];
            $values[$field] = $existing->{$field};

            $this->actingAs($admin, 'web')
                ->from('/cms/categories')
                ->post('/cms/categories', $values)
                ->assertRedirect('/cms/categories')
                ->assertSessionHasErrors($field);
        }

        $existing->delete();

        $this->actingAs($admin, 'web')
            ->post('/cms/categories', [
                'name_en' => $existing->name_en,
                'name_zh' => $existing->name_zh,
                'name_my' => $existing->name_my,
                'slug' => $existing->slug,
            ])
            ->assertRedirect('/cms/categories')
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Category::query()->where('slug', $existing->slug)->count());
    }

    public function test_admins_can_manage_contacts_and_gallery(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')
            ->post('/cms/contacts', ['contact_point' => '+959111111111'])
            ->assertRedirect('/cms/contacts');

        $contact = Contact::query()->firstOrFail();
        $this->actingAs($admin, 'web')
            ->put('/cms/contacts/' . $contact->id, ['contact_point' => 'support@cg-net.test'])
            ->assertRedirect('/cms/contacts');

        $this->actingAs($admin, 'web')
            ->post('/cms/gallery', [
                'label' => 'Office',
                'image' => $this->fakeImage('gallery.jpg'),
            ])
            ->assertRedirect('/cms/gallery');

        $this->assertDatabaseHas('contacts', ['contact_point' => 'support@cg-net.test']);
        $this->assertDatabaseCount('gallery', 1);
    }

    public function test_admins_can_bulk_delete_promotions(): void
    {
        $admin = Admin::factory()->create();
        $first = Promotion::factory()->create(['title_en' => 'First promo']);
        $second = Promotion::factory()->create(['title_en' => 'Second promo']);

        $this->actingAs($admin, 'web')
            ->from('/cms/promotions')
            ->delete('/cms/promotions/bulk-destroy', ['ids' => [$first->id, $second->id]])
            ->assertRedirect('/cms/promotions')
            ->assertSessionHas('success', 'common.bulk_deleted');

        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
    }

    public function test_bulk_delete_skips_categories_that_have_news(): void
    {
        $admin = Admin::factory()->create();
        $inUse = Category::factory()->create();
        $unused = Category::factory()->create();
        News::factory()->create(['category_id' => $inUse->id]);

        $this->actingAs($admin, 'web')
            ->from('/cms/categories')
            ->delete('/cms/categories/bulk-destroy', ['ids' => [$inUse->id, $unused->id]])
            ->assertRedirect('/cms/categories')
            ->assertSessionHas('success', 'common.bulk_deleted')
            ->assertSessionHasErrors(['delete' => 'cms.bulk_delete_partial']);

        $this->assertDatabaseHas('categories', ['id' => $inUse->id, 'deleted_at' => null]);
        $this->assertSoftDeleted($unused);
    }

    public function test_active_announcement_and_promotion_titles_are_pushed_once(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $user->deviceTokens()->create(['token' => 'fcm-title-push']);

        $announcement = Announcement::factory()->create([
            'type' => AnnouncementType::Announce,
            'title_en' => 'Announce EN',
            'title_zh' => 'Announce ZH',
            'title_my' => 'Announce MY',
            'is_active' => true,
            'start_date' => now()->subHour(),
            'end_date' => now()->addHour(),
        ]);
        $systemAnnouncement = Announcement::factory()->create([
            'type' => AnnouncementType::System,
            'title_en' => 'System EN',
            'title_zh' => 'System ZH',
            'title_my' => 'System MY',
            'is_active' => true,
            'start_date' => now()->subHour(),
            'end_date' => now()->addHour(),
        ]);
        Announcement::factory()
            ->inactive()
            ->create([
                'start_date' => now()->subHour(),
                'end_date' => now()->addHour(),
            ]);
        Announcement::factory()->upcoming()->create();
        Announcement::factory()->expired()->create();
        $undatedAnnouncement = Announcement::factory()->create([
            'type' => AnnouncementType::Announce,
            'is_active' => true,
            'start_date' => null,
            'end_date' => null,
        ]);

        $promotion = Promotion::factory()->create([
            'title_en' => 'Promo EN',
            'title_zh' => 'Promo ZH',
            'title_my' => 'Promo MY',
            'is_active' => true,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);
        $cmsPromotion = Promotion::factory()->create([
            'title_en' => 'CMS Promo EN',
            'title_zh' => 'CMS Promo ZH',
            'title_my' => 'CMS Promo MY',
            'is_active' => true,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);
        Promotion::factory()->create([
            'is_active' => false,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);
        Promotion::factory()->create([
            'is_active' => true,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ]);
        Promotion::factory()->create([
            'is_active' => true,
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ]);

        $service = app(PushNotificationService::class);

        $this->assertSame(5, $service->processDueTitles());
        $this->assertSame(0, $service->processDueTitles());

        Notification::assertSentTo(
            $user,
            CampaignNotification::class,
            fn(CampaignNotification $notification): bool => $notification->title === 'Announce EN' &&
                $notification->body === $announcement->content_en &&
                $notification->category === 'announcement',
        );
        Notification::assertSentTo(
            $user,
            CampaignNotification::class,
            fn(CampaignNotification $notification): bool => $notification->title === 'System EN' &&
                $notification->body === $systemAnnouncement->content_en &&
                $notification->category === 'system',
        );
        Notification::assertSentTo(
            $user,
            CampaignNotification::class,
            fn(CampaignNotification $notification): bool => $notification->title === $undatedAnnouncement->title_en &&
                $notification->body === $undatedAnnouncement->content_en &&
                $notification->category === 'announcement',
        );
        Notification::assertSentTo(
            $user,
            CampaignNotification::class,
            fn(CampaignNotification $notification): bool => $notification->title === 'Promo EN' &&
                $notification->body === $promotion->description_en &&
                $notification->category === 'promotion',
        );
        Notification::assertSentTo(
            $user,
            CampaignNotification::class,
            fn(CampaignNotification $notification): bool => $notification->title === 'CMS Promo EN' &&
                $notification->body === $cmsPromotion->description_en &&
                $notification->category === 'promotion',
        );
        Notification::assertSentTimes(CampaignNotification::class, 5);

        $this->assertNotNull($announcement->fresh()->push_sent_at);
        $this->assertNotNull($systemAnnouncement->fresh()->push_sent_at);
        $this->assertNotNull($promotion->fresh()->push_sent_at);
        $this->assertNotNull($cmsPromotion->fresh()->push_sent_at);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'category' => NotificationCategory::Announcement->value,
            'action_type' => 'announcement',
            'action_id' => (string) $announcement->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'category' => NotificationCategory::System->value,
            'action_type' => 'announcement',
            'action_id' => (string) $systemAnnouncement->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'category' => NotificationCategory::Announcement->value,
            'action_type' => 'announcement',
            'action_id' => (string) $undatedAnnouncement->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'category' => NotificationCategory::Promotion->value,
            'action_type' => 'promotion',
            'action_id' => (string) $promotion->id,
        ]);
        $token = $user->createToken('campaign-notifications-test')->plainTextToken;
        $this->withToken($token, 'Bearer')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Announce EN',
                'body' => $announcement->content_en,
                'category' => 'announcement',
                'action_type' => 'announcement',
                'action_id' => (string) $announcement->id,
                'is_read' => false,
            ])
            ->assertJsonFragment([
                'title' => 'System EN',
                'body' => $systemAnnouncement->content_en,
                'category' => 'system',
                'action_type' => 'announcement',
                'action_id' => (string) $systemAnnouncement->id,
                'is_read' => false,
            ])
            ->assertJsonFragment([
                'title' => 'Promo EN',
                'body' => $promotion->description_en,
                'category' => 'promotion',
                'action_type' => 'promotion',
                'action_id' => (string) $promotion->id,
                'is_read' => false,
            ]);
        $this->assertSame(3, Announcement::query()->whereNull('push_sent_at')->count());
        $this->assertSame(3, Promotion::query()->whereNull('push_sent_at')->count());
    }

    public function test_campaign_pushes_respect_the_users_category_preferences(): void
    {
        Notification::fake();

        $user = User::factory()->create(['system_enabled' => false]);
        $user->deviceTokens()->create(['token' => 'fcm-category-preference']);
        $generalOptOutUser = User::factory()->create(['general_enabled' => false]);
        $generalOptOutUser->deviceTokens()->create(['token' => 'fcm-general-preference']);
        $systemAnnouncement = Announcement::factory()->create([
            'type' => AnnouncementType::System,
            'is_active' => true,
            'start_date' => now()->subHour(),
            'end_date' => now()->addHour(),
        ]);
        $announcement = Announcement::factory()->create([
            'type' => AnnouncementType::Announce,
            'is_active' => true,
            'start_date' => now()->subHour(),
            'end_date' => now()->addHour(),
        ]);

        $this->assertSame(1, app(PushNotificationService::class)->processDueTitles());

        Notification::assertSentTo($user, CampaignNotification::class, function (
            CampaignNotification $notification,
        ) use ($announcement): bool {
            return $notification->category === NotificationCategory::Announcement->value &&
                $notification->actionId === (string) $announcement->id;
        });
        Notification::assertNotSentTo($user, CampaignNotification::class, function (
            CampaignNotification $notification,
        ) use ($systemAnnouncement): bool {
            return $notification->actionId === (string) $systemAnnouncement->id;
        });
        Notification::assertSentTo($generalOptOutUser, CampaignNotification::class, function (
            CampaignNotification $notification,
        ) use ($announcement): bool {
            return $notification->category === NotificationCategory::Announcement->value &&
                $notification->actionId === (string) $announcement->id;
        });
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'category' => NotificationCategory::System->value,
            'action_id' => (string) $systemAnnouncement->id,
            'sent_at' => null,
        ]);
        $this->assertNull($systemAnnouncement->fresh()->push_sent_at);
    }

    public function test_admin_pushes_respect_the_general_notification_preference(): void
    {
        Notification::fake();

        $enabledUser = User::factory()->create([
            'announcements_enabled' => false,
            'system_enabled' => false,
            'promotion_enabled' => false,
            'general_enabled' => true,
        ]);
        $enabledUser->deviceTokens()->create(['token' => 'fcm-general-enabled']);
        $disabledUser = User::factory()->create(['general_enabled' => false]);
        $disabledUser->deviceTokens()->create(['token' => 'fcm-general-disabled']);

        $recipients = app(PushNotificationService::class)->pushNow([
            'title_en' => 'Admin push EN',
            'title_zh' => 'Admin push ZH',
            'title_my' => 'Admin push MY',
        ]);

        $this->assertSame(1, $recipients);
        Notification::assertSentTo($enabledUser, AdminPushNotification::class);
        Notification::assertNotSentTo($disabledUser, AdminPushNotification::class);
    }

    public function test_title_push_is_not_marked_sent_when_no_device_is_registered(): void
    {
        Notification::fake();

        $announcement = Announcement::factory()->create([
            'is_active' => true,
            'start_date' => now()->subHour(),
            'end_date' => now()->addHour(),
        ]);

        $this->assertSame(0, app(PushNotificationService::class)->processDueTitles());
        $this->assertNull($announcement->fresh()->push_sent_at);
        Notification::assertNothingSent();
    }

    private function fakeImage(string $name = 'promo.jpg'): UploadedFile
    {
        // Minimal 1x1 JPEG so tests do not require the GD extension.
        $jpeg = base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAGcP//EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAQUCf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQMBAT8Bf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQIBAT8Bf//Z',
        );

        return UploadedFile::fake()->createWithContent($name, $jpeg);
    }
}
