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
     * @var list<string>
     */
    private const TOWNSHIP_CODES = [
        'MaYaKa',
        'AhGaYa',
        'KhaAhHla',
        'SaKaNa',
        'DaGaSa',
        'LaMaNa',
        'PaBaTa',
        'YaKaNa',
    ];

    public static function phone(): string
    {
        return '+959'.fake()->unique()->numerify('########');
    }

    public static function name(): string
    {
        return fake()->randomElement([
            ...self::ENGLISH_NAMES,
            ...self::MYANMAR_NAMES,
        ]);
    }

    public static function nrc(): string
    {
        $state = fake()->numberBetween(1, 14);
        $code = fake()->randomElement(self::TOWNSHIP_CODES);

        return $state.'/'.$code.'(N)'.fake()->numerify('######');
    }

    public static function address(): string
    {
        $streetNo = fake()->numberBetween(1, 240);
        $quarters = ['Bahan', 'Kamayut', 'Chanayethazan', 'Taunggyi', 'Pathein', 'Monywa', 'လသာ', 'စမ်းချောင်း'];

        return $streetNo.' '.fake()->randomElement($quarters).', Myanmar';
    }
}
