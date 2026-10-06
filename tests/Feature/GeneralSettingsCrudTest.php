<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Faq;
use App\Models\SupportContact;
use App\Models\TermAndCondition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralSettingsCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_terms_faqs_and_support_contacts(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'web');

        $this->post('/settings/general/terms-and-conditions', $this->localizedContent('Terms'))
            ->assertRedirect('/settings/general');
        $terms = TermAndCondition::query()->firstOrFail();
        $this->assertSame('Terms English', $terms->title_en);

        $this->put("/settings/general/terms-and-conditions/{$terms->id}", $this->localizedContent('Updated terms'))
            ->assertRedirect('/settings/general');
        $this->assertSame('Updated terms English', $terms->fresh()->title_en);

        $this->delete("/settings/general/terms-and-conditions/{$terms->id}")
            ->assertRedirect('/settings/general');
        $this->assertDatabaseMissing('term_and_conditions', ['id' => $terms->id]);

        $this->post('/settings/general/faqs', $this->localizedContent('FAQ'))
            ->assertRedirect('/settings/general');
        $faq = Faq::query()->firstOrFail();
        $this->assertSame('FAQ English', $faq->title_en);

        $this->put("/settings/general/faqs/{$faq->id}", $this->localizedContent('Updated FAQ'))
            ->assertRedirect('/settings/general');
        $this->assertSame('Updated FAQ English', $faq->fresh()->title_en);

        $this->delete("/settings/general/faqs/{$faq->id}")
            ->assertRedirect('/settings/general');
        $this->assertDatabaseMissing('faqs', ['id' => $faq->id]);

        $this->post('/settings/general/support-contacts', ['phone' => '+95 9 123 456 789'])
            ->assertRedirect('/settings/general');
        $contact = SupportContact::query()->firstOrFail();
        $this->assertSame('+95 9 123 456 789', $contact->phone);

        $this->put("/settings/general/support-contacts/{$contact->id}", ['phone' => '+95 9 987 654 321'])
            ->assertRedirect('/settings/general');
        $this->assertSame('+95 9 987 654 321', $contact->fresh()->phone);

        $this->delete("/settings/general/support-contacts/{$contact->id}")
            ->assertRedirect('/settings/general');
        $this->assertDatabaseMissing('support_contacts', ['id' => $contact->id]);
    }

    public function test_create_endpoints_validate_required_content(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')
            ->post('/settings/general/terms-and-conditions', [])
            ->assertSessionHasErrors(['title_en', 'title_zh', 'title_my', 'description_en', 'description_zh', 'description_my']);

        $this->actingAs($admin, 'web')
            ->post('/settings/general/faqs', [])
            ->assertSessionHasErrors(['title_en', 'title_zh', 'title_my', 'description_en', 'description_zh', 'description_my']);

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
}
