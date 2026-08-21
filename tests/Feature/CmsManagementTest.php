<?php

namespace Tests\Feature;

use App\Enums\LanguagePref;
use App\Enums\NewsStatus;
use App\Models\Admin;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Contact;
use App\Models\News;
use App\Models\Promotion;
use App\Models\Tag;
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
        Promotion::factory()->create(['title' => 'Monsoon offer', 'lang' => LanguagePref::En]);
        Promotion::factory()->create(['title' => 'Chinese banner', 'lang' => LanguagePref::Zh, 'is_active' => false]);

        $this->actingAs($admin, 'web')
            ->get('/cms/promotions?search=Monsoon&lang=en&status=active')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cms/Promotions/Index')
                ->has('items.data', 1)
                ->where('items.data.0.title', 'Monsoon offer'));
    }

    public function test_admins_can_create_update_and_delete_a_promotion(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')
            ->post('/cms/promotions', [
                'title' => 'Summer promo',
                'description' => 'Save this month',
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-31',
                'is_active' => '1',
                'lang' => 'en',
                'image' => UploadedFile::fake()->image('promo.jpg'),
            ])
            ->assertRedirect('/cms/promotions');

        $promotion = Promotion::query()->firstOrFail();
        $this->assertSame('Summer promo', $promotion->title);
        Storage::disk('public')->assertExists($promotion->image_path);

        $this->actingAs($admin, 'web')
            ->put('/cms/promotions/'.$promotion->id, [
                'title' => 'Summer promo updated',
                'description' => 'Save this month',
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-31',
                'is_active' => '0',
                'lang' => 'my',
            ])
            ->assertRedirect('/cms/promotions');

        $this->assertSame('Summer promo updated', $promotion->fresh()->title);
        $this->assertFalse($promotion->fresh()->is_active);

        $this->actingAs($admin, 'web')
            ->delete('/cms/promotions/'.$promotion->id)
            ->assertRedirect('/cms/promotions');

        $this->assertSoftDeleted($promotion);
    }

    public function test_existing_banners_are_managed_under_cms(): void
    {
        $admin = Admin::factory()->create();
        Banner::factory()->create(['title' => 'Homepage hero', 'lang' => LanguagePref::En]);

        $this->actingAs($admin, 'web')
            ->get('/cms/banners')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cms/Banners/Index')
                ->where('items.data.0.title', 'Homepage hero'));

        $this->actingAs($admin, 'web')
            ->get('/banners')
            ->assertNotFound();
    }

    public function test_news_can_be_created_with_category_and_tags(): void
    {
        $admin = Admin::factory()->create();
        $category = Category::factory()->create(['name' => 'Offers', 'slug' => 'offers']);
        $tag = Tag::factory()->create(['name' => 'Fiber', 'slug' => 'fiber']);

        $this->actingAs($admin, 'web')
            ->post('/cms/news', [
                'category_id' => $category->id,
                'title' => 'Coverage update',
                'slug' => 'coverage-update',
                'content' => 'Fiber is now live.',
                'status' => NewsStatus::Published->value,
                'lang' => 'en',
                'tag_ids' => [$tag->id],
                'image' => UploadedFile::fake()->image('news.jpg'),
            ])
            ->assertRedirect('/cms/news');

        $news = News::query()->firstOrFail();
        $news->load('tags');
        $this->assertSame($category->id, $news->category_id);
        $this->assertEqualsCanonicalizing([$tag->id], $news->tags->pluck('id')->all());
        Storage::disk('public')->assertExists($news->image_path);
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

    public function test_admins_can_manage_contacts_tags_and_gallery(): void
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
            ->post('/cms/tags', ['name' => 'Promo', 'slug' => 'promo', 'lang' => 'en'])
            ->assertRedirect('/cms/tags');

        $this->actingAs($admin, 'web')
            ->post('/cms/gallery', [
                'label' => 'Office',
                'lang' => 'en',
                'image' => UploadedFile::fake()->image('gallery.jpg'),
            ])
            ->assertRedirect('/cms/gallery');

        $this->assertDatabaseHas('contacts', ['contact_point' => 'support@cg-net.test']);
        $this->assertDatabaseHas('tags', ['slug' => 'promo']);
        $this->assertDatabaseCount('gallery', 1);
    }
}
