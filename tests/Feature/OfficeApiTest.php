<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_office_api_returns_localized_details_and_available_contact_links(): void
    {
        $office = $this->makeOffice('Central Office', 'Main Street', '01');
        $office->update([
            'name_mm' => 'ဗဟိုရုံး',
            'name_zh' => '中央办公室',
            'address_mm' => 'လမ်းမကြီး',
            'address_zh' => '主街',
            'phone_num2' => '09123456780',
            'telegram_link' => 'https://t.me/central',
            'viber_link' => 'viber://chat?number=09123456789',
        ]);
        $this->makeOffice('Deleted Office', 'Deleted Street', '02')->delete();

        $this->getJson('/api/offices')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $office->id)
            ->assertJsonPath('data.0.code', '01')
            ->assertJsonPath('data.0.name.en', 'Central Office')
            ->assertJsonPath('data.0.name.my', 'ဗဟိုရုံး')
            ->assertJsonPath('data.0.name.zh', '中央办公室')
            ->assertJsonPath('data.0.address.en', 'Main Street')
            ->assertJsonPath('data.0.address.my', 'လမ်းမကြီး')
            ->assertJsonPath('data.0.address.zh', '主街')
            ->assertJsonPath('data.0.phone_numbers', ['09123456789', '09123456780'])
            ->assertJsonPath('data.0.social_links.telegram', 'https://t.me/central')
            ->assertJsonPath('data.0.social_links.viber', 'viber://chat?number=09123456789')
            ->assertJsonMissingPath('data.0.social_links.wechat');
    }
}
