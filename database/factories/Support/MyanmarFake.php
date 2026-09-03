<?php

namespace Database\Factories\Support;

final class MyanmarFake
{
    /**
     * @var list<string>
     */
    private const ENGLISH_NAMES = [
        'Aung Ko Ko',
        'Su Su Win',
        'Kyaw Min Thu',
        'Hnin Pwint',
        'Zaw Zaw',
        'May Myat Noe',
        'Thura Lin',
        'Nandar Hlaing',
        'Min Khant',
        'Aye Chan Moe',
    ];

    /**
     * @var list<string>
     */
    private const MYANMAR_NAMES = [
        'အောင်ကိုကို',
        'စုစုဝင်း',
        'ကျော်မင်းသူ',
        'နှင်းပွင့်',
        'ဇော်ဇော်',
        'မေမြတ်နိုး',
        'သူရလင်း',
        'နန္ဒာလှိုင်',
        'မင်းခန့်',
        'အေးချမ်းမိုး',
    ];

    /**
     * @var list<array{dial: string, length: int}>
     */
    private const PHONE_COUNTRIES = [
        ['dial' => '959', 'length' => 8], // Myanmar +959
        ['dial' => '66', 'length' => 9],  // Thailand +66
        ['dial' => '86', 'length' => 11], // China +86
    ];

    public static function phone(?string $country = null): string
    {
        $option = match ($country) {
            'mm', 'myanmar' => self::PHONE_COUNTRIES[0],
            'th', 'thailand' => self::PHONE_COUNTRIES[1],
            'cn', 'china' => self::PHONE_COUNTRIES[2],
            default => fake()->randomElement(self::PHONE_COUNTRIES),
        };

        return '+'.$option['dial'].fake()->unique()->numerify(str_repeat('#', $option['length']));
    }

    public static function name(): string
    {
        return fake()->randomElement([
            ...self::ENGLISH_NAMES,
            ...self::MYANMAR_NAMES,
        ]);
    }

    public static function address(): string
    {
        $streetNo = fake()->numberBetween(1, 240);
        $quarters = ['Bahan', 'Kamayut', 'Chanayethazan', 'Taunggyi', 'Pathein', 'Monywa', 'လသာ', 'စမ်းချောင်း'];

        return $streetNo.' '.fake()->randomElement($quarters).', Myanmar';
    }
}
