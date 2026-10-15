<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Faq;
use App\Models\SupportContact;
use App\Models\TermAndCondition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class GeneralSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_settings_index_shows_terms_faqs_and_support_contacts(): void
    {
        $admin = Admin::factory()->create();
        TermAndCondition::query()->create([
            'title_en' => 'Terms title',
            'title_zh' => '条款标题',
            'title_my' => 'စည်းမျဉ်း ခေါင်းစဉ်',
            'description_en' => 'Terms description',
            'description_zh' => '条款说明',
            'description_my' => 'စည်းမျဉ်း ဖော်ပြချက်',
        ]);
        Faq::query()->create([
            'title_en' => 'FAQ title',
            'title_zh' => '常见问题标题',
            'title_my' => 'မေးခွန်း ခေါင်းစဉ်',
            'description_en' => 'FAQ description',
            'description_zh' => '常见问题说明',
            'description_my' => 'မေးခွန်း ဖော်ပြချက်',
        ]);
        $supportContact = new SupportContact();
        $supportContact->phone = '+123456789';
        $supportContact->save();

        $this->actingAs($admin, 'web')
            ->get('/settings/general')
            ->assertOk()
            ->assertInertia(
                fn(Assert $inertia) => $inertia
                    ->component('Settings/GeneralSettings/Index')
                    ->has('termsAndConditions', 1)
                    ->where('termsAndConditions.0.title_en', 'Terms title')
                    ->has('faqs', 1)
                    ->where('faqs.0.title_en', 'FAQ title')
                    ->has('supportContacts', 1)
                    ->where('supportContacts.0.phone', '+123456789'),
            );
    }

    public function test_admin_can_create_update_and_delete_terms_faqs_and_support_contacts(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'web');

        $this->post('/settings/general/terms-and-conditions', $this->localizedContent('Terms'))->assertRedirect(
            '/settings/general',
        );
        $terms = TermAndCondition::query()->firstOrFail();
        $this->assertSame('Terms English', $terms->title_en);

        $this->put(
            "/settings/general/terms-and-conditions/{$terms->id}",
            $this->localizedContent('Updated terms'),
        )->assertRedirect('/settings/general');
        $this->assertSame('Updated terms English', $terms->fresh()->title_en);

        $this->delete("/settings/general/terms-and-conditions/{$terms->id}")->assertRedirect('/settings/general');
        $this->assertDatabaseMissing('term_and_conditions', ['id' => $terms->id]);

        $this->post('/settings/general/faqs', $this->localizedContent('FAQ'))->assertRedirect('/settings/general');
        $faq = Faq::query()->firstOrFail();
        $this->assertSame('FAQ English', $faq->title_en);

        $this->put("/settings/general/faqs/{$faq->id}", $this->localizedContent('Updated FAQ'))->assertRedirect(
            '/settings/general',
        );
        $this->assertSame('Updated FAQ English', $faq->fresh()->title_en);

        $this->delete("/settings/general/faqs/{$faq->id}")->assertRedirect('/settings/general');
        $this->assertDatabaseMissing('faqs', ['id' => $faq->id]);

        $this->post('/settings/general/support-contacts', ['phone' => '+95 9 123 456 789'])->assertRedirect(
            '/settings/general',
        );
        $contact = SupportContact::query()->firstOrFail();
        $this->assertSame('+95 9 123 456 789', $contact->phone);

        $this->put("/settings/general/support-contacts/{$contact->id}", [
            'phone' => '+95 9 987 654 321',
        ])->assertRedirect('/settings/general');
        $this->assertSame('+95 9 987 654 321', $contact->fresh()->phone);

        $this->delete("/settings/general/support-contacts/{$contact->id}")->assertRedirect('/settings/general');
        $this->assertDatabaseMissing('support_contacts', ['id' => $contact->id]);
    }

    public function test_create_endpoints_validate_required_content(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')
            ->post('/settings/general/terms-and-conditions', [])
            ->assertSessionHasErrors([
                'title_en',
                'title_zh',
                'title_my',
                'description_en',
                'description_zh',
                'description_my',
            ]);

        $this->actingAs($admin, 'web')
            ->post('/settings/general/faqs', [])
            ->assertSessionHasErrors([
                'title_en',
                'title_zh',
                'title_my',
                'description_en',
                'description_zh',
                'description_my',
            ]);

        $this->actingAs($admin, 'web')
            ->post('/settings/general/support-contacts', [])
            ->assertSessionHasErrors(['phone']);
    }

    /**
     * @return array<string, string>
     */
    private function localizedContent(string $prefix): array
    {
        return [
            'title_en' => "{$prefix} English",
            'title_zh' => "{$prefix} Chinese",
            'title_my' => "{$prefix} Burmese",
            'description_en' => "{$prefix} English description",
            'description_zh' => "{$prefix} Chinese description",
            'description_my' => "{$prefix} Burmese description",
        ];
    }
    public function test_term_and_condition_api_returns_term_and_condition_records(): void
    {
        $term = TermAndCondition::query()->create([
            'title_en' => 'Terms English',
            'title_zh' => 'Terms Chinese',
            'title_my' => 'Terms Burmese',
            'description_en' => 'Terms English description',
            'description_zh' => 'Terms Chinese description',
            'description_my' => 'Terms Burmese description',
        ]);

        $this->getJson('/api/general-settings/term-and-condition')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $term->id)
            ->assertJsonPath('data.0.title.en', 'Terms English')
            ->assertJsonPath('data.0.title.zh', 'Terms Chinese')
            ->assertJsonPath('data.0.title.my', 'Terms Burmese')
            ->assertJsonPath('data.0.description.en', 'Terms English description')
            ->assertJsonPath('data.0.description.zh', 'Terms Chinese description')
            ->assertJsonPath('data.0.description.my', 'Terms Burmese description');
    }

    public function test_faq_api_returns_faq_records(): void
    {
        $faq = Faq::query()->create([
            'title_en' => 'FAQ English',
            'title_zh' => 'FAQ Chinese',
            'title_my' => 'FAQ Burmese',
            'description_en' => 'FAQ English description',
            'description_zh' => 'FAQ Chinese description',
            'description_my' => 'FAQ Burmese description',
        ]);

        $this->getJson('/api/general-settings/faq')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $faq->id)
            ->assertJsonPath('data.0.title.en', 'FAQ English')
            ->assertJsonPath('data.0.title.zh', 'FAQ Chinese')
            ->assertJsonPath('data.0.title.my', 'FAQ Burmese')
            ->assertJsonPath('data.0.description.en', 'FAQ English description')
            ->assertJsonPath('data.0.description.zh', 'FAQ Chinese description')
            ->assertJsonPath('data.0.description.my', 'FAQ Burmese description');
    }

    public function test_support_contact_api_returns_support_contact_records(): void
    {
        $contact = SupportContact::query()->create([
            'phone' => '+95 9 123 456 789',
        ]);

        $this->getJson('/api/general-settings/support-contact')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $contact->id)
            ->assertJsonPath('data.0.phone', '+95 9 123 456 789');
    }
}
