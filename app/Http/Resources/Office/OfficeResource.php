<?php

namespace App\Http\Resources\Office;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfficeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->cd,
            'name' => [
                'en' => $this->name_en,
                'my' => $this->name_mm,
                'zh' => $this->name_zh,
            ],
            'address' => [
                'en' => $this->address_en,
                'my' => $this->address_mm,
                'zh' => $this->address_zh,
            ],
            'phone_numbers' => array_values(
                array_filter(
                    [$this->phone_num1, $this->phone_num2, $this->phone_num3],
                    fn(?string $phone): bool => filled($phone),
                ),
            ),
            'social_links' => array_filter(
                [
                    'telegram' => $this->telegram_link,
                    'wechat' => $this->wechat_link,
                    'viber' => $this->viber_link,
                ],
                fn(?string $link): bool => filled($link),
            ),
        ];
    }
}
