<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Canonical phone format for customers: digits only, country code first, no "+"
 * (for example 95922345678). Everything that stores or looks up users.phone must
 * go through normalize() so the same number is never saved in two spellings.
 */
final class PhoneNumber
{
    /**
     * Countries the service supports, as a regex over the canonical format.
     * Thailand and China use broad mobile prefixes and national lengths rather
     * than allocated sub-prefix lists, which may change over time. The matching
     * rules live in resources/js/lib/phone.ts.
     */
    public const SUPPORTED_PATTERN = '/^(959[2-9]\d{7,10}|66(?:14\d{7}|[689]\d{8})|861[3-9]\d{9})$/';

    /** @throws InvalidArgumentException when the value cannot be a phone number */
    public static function normalize(string $phone): string
    {
        $phone = preg_replace('/[\s().-]+/', '', trim($phone)) ?? '';

        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }

        if (str_starts_with($phone, '09')) {
            $phone = '+95' . substr($phone, 1);
        } elseif (str_starts_with($phone, '06') || str_starts_with($phone, '08')) {
            $phone = '+66' . substr($phone, 1);
        }

        $phone = ltrim($phone, '+');

        if (str_starts_with($phone, '950')) {
            $phone = '95' . substr($phone, 3);
        } elseif (str_starts_with($phone, '660')) {
            $phone = '66' . substr($phone, 3);
        }

        if (!preg_match('/^[1-9][0-9]{7,14}$/', $phone)) {
            throw new InvalidArgumentException('Invalid phone number.');
        }

        return $phone;
    }

    /** Like normalize(), but never throws: returns the digits typed so validation can reject them. */
    public static function normalizeLeniently(mixed $phone): ?string
    {
        if (!is_string($phone)) {
            return null;
        }

        try {
            return self::normalize($phone);
        } catch (InvalidArgumentException) {
            return preg_replace('/\D+/', '', $phone) ?? '';
        }
    }

    public static function isSupported(string $normalized): bool
    {
        return preg_match(self::SUPPORTED_PATTERN, $normalized) === 1;
    }
}
