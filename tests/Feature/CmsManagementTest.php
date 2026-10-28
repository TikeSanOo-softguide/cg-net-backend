<?php

namespace Tests\Feature;

use App\Enums\NewsStatus;
use App\Models\Admin;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Gallery;
use App\Models\News;
use App\Models\Promotion;
use App\Support\CmsPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        $admin = Admin::factory()->create(['email' => 'admin@cg-net.test']);

        $permissions = collect(CmsPermissions::all())->map(
            fn (string $name) => Permission::query()->firstOrCreate([
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
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cms/Promotions/Index')
                ->has('items.data', 1)
                ->where('items.data.0.title_en', 'Monsoon offer'));
    }

    public function test_admins_can_create_update_and_delete_a_promotion(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')
            ->post('/cms/promotions', [
                'title_en' => 'Summer promo',
                'title_zh' => '夏季促销',
                'title_my' => 'နွေရာသီ ပရိုမိုးရှင်း',
                'description_en' => 'Save this month',
                'description_zh' => '本月优惠',
                'description_my' => 'ဒီလ လျှော့စျေး',
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-31',
                'is_active' => '1',
                'image' => UploadedFile::fake()->image('promo.jpg'),
            ])
            ->assertRedirect('/cms/promotions');

        $promotion = Promotion::query()->firstOrFail();
        $this->assertSame('Summer promo', $promotion->title_en);
        $this->assertSame('夏季促销', $promotion->title_zh);
        $this->assertSame('နွေရာသီ ပရိုမိုးရှင်း', $promotion->title_my);
        $this->assertSame('Save this month', $promotion->description_en);
        $this->assertSame('summer-promo', $promotion->slug);
        Storage::disk('public')->assertExists($promotion->image_url);

        $this->actingAs($admin, 'web')
            ->put('/cms/promotions/'.$promotion->id, [
                'title_en' => 'Summer promo updated',
                'title_zh' => '夏季促销',
                'title_my' => 'နွေရာသီ ပရိုမိုးရှင်း',
                'description_en' => 'Save this month',
                'description_zh' => '本月优惠',
                'description_my' => 'ဒီလ လျှော့စျေး',
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-31',
                'is_active' => '0',
            ])
            ->assertRedirect('/cms/promotions');

        $this->assertSame('Summer promo updated', $promotion->fresh()->title_en);
        $this->assertSame('summer-promo-updated', $promotion->fresh()->slug);
        $this->assertFalse($promotion->fresh()->is_active);

        $this->actingAs($admin, 'web')
            ->delete('/cms/promotions/'.$promotion->id)
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
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cms/Banners/Index')
                ->has('items.data', 1));

        $this->actingAs($admin, 'web')
            ->get('/banners')
            ->assertNotFound();

        $this->actingAs($admin, 'web')
            ->post('/cms/banners', [
                'sort_order' => 4,
                'is_active' => '1',
                'image_en' => UploadedFile::fake()->image('banner-en.jpg'),
                'image_zh' => UploadedFile::fake()->image('banner-zh.jpg'),
                'image_my' => UploadedFile::fake()->image('banner-my.jpg'),
            ])
            ->assertRedirect('/cms/banners');

        $created = Banner::query()->where('sort_order', 4)->firstOrFail();
        Storage::disk('public')->assertExists($created->image_url_en);
        Storage::disk('public')->assertExists($created->image_url_zh);
        Storage::disk('public')->assertExists($created->image_url_my);
    }

    public function test_news_can_be_created_with_category(): void
    {
        $admin = Admin::factory()->create();
        $category = Category::factory()->create([
            'name_en' => 'Offers',
            'name_zh' => '优惠',
            'name_my' => 'လျှော့စျေး',
            'slug' => 'offers',
        ]);
        $this->actingAs($admin, 'web')
            ->post('/cms/news', [
                'category_id' => $category->id,
                'title_en' => 'Coverage update',
                'title_zh' => '覆盖更新',
                'title_my' => 'ကွန်ရက် အပ်ဒိတ်',
                'slug' => 'coverage-update',
                'description_en' => 'Fiber is now live.',
                'description_zh' => '光纤已开通。',
                'description_my' => 'ဖိုင်ဘာ ရရှိပါပြီ။',
                'status' => NewsStatus::Published->value,
                'image' => UploadedFile::fake()->image('news.jpg'),
            ])
            ->assertRedirect('/cms/news');

        $news = News::query()->firstOrFail();
        $this->assertSame($category->id, $news->category_id);
        $this->assertSame('Coverage update', $news->title_en);
        $this->assertSame('覆盖更新', $news->title_zh);
        $this->assertSame('Fiber is now live.', $news->description_en);
        $this->assertSame('ဖိုင်ဘာ ရရှိပါပြီ။', $news->description_my);
        Storage::disk('public')->assertExists($news->image_url);
    }

    public function test_admins_can_create_a_category_with_locale_names(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')
            ->post('/cms/categories', [
                'name_en' => 'Offers',
                'name_zh' => '优惠',
                'name_my' => 'လျှော့စျေး',
            ])
            ->assertRedirect('/cms/categories');

        $this->assertDatabaseHas('categories', [
            'name_en' => 'Offers',
            'name_zh' => '优惠',
            'name_my' => 'လျှော့စျေး',
            'slug' => 'offers',
        ]);
    }

    public function test_category_with_news_cannot_be_deleted(): void
    {
        $admin = Admin::factory()->create();
        $category = Category::factory()->create();
        News::factory()->create(['category_id' => $category->id]);

        $this->actingAs($admin, 'web')
            ->from('/cms/categories')
            ->delete('/cms/categories/'.$category->id)
            ->assertRedirect('/cms/categories')
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'deleted_at' => null]);
    }

    public function test_admins_can_manage_contacts_and_gallery(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')
            ->post('/cms/contacts', ['contact_point' => '+959111111111'])
            ->assertRedirect('/cms/contacts');

        $contact = Contact::query()->firstOrFail();
        $this->actingAs($admin, 'web')
            ->put('/cms/contacts/'.$contact->id, ['contact_point' => 'support@cg-net.test'])
            ->assertRedirect('/cms/contacts');

        $this->actingAs($admin, 'web')
            ->post('/cms/gallery', [
                'label_en' => 'Office',
                'label_zh' => '办公室',
                'label_my' => 'ရုံး',
                'image' => UploadedFile::fake()->image('gallery.jpg'),
            ])
            ->assertRedirect('/cms/gallery');

        $gallery = Gallery::query()->firstOrFail();
        $this->assertSame('Office', $gallery->label_en);
        $this->assertSame('办公室', $gallery->label_zh);
        $this->assertSame('ရုံး', $gallery->label_my);
        Storage::disk('public')->assertExists($gallery->image_url);
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
}
