<?php

namespace App\Enums;

/**
 * Stable API message codes. Mobile maps these to localized copy.
 * `message()` is English for logs / Postman only — never the UI contract.
 *
 * MSG-AUTH-002 (login successful) is not an error and is not listed here.
 * MSG-AUTH-003 (invalid username) is unused: login is phone + PIN.
 * MSG-USER-001 is unused on login so we do not leak whether a phone exists.
 */
enum MessageCode: string
{
    case AuthIncorrectPassword = 'MSG-AUTH-001';
    case AuthAccountLocked = 'MSG-AUTH-004';
    case AuthOtpFailed = 'MSG-AUTH-005';
    case AuthPhoneUnsupported = 'MSG-AUTH-006';
    case AuthPhoneRequired = 'MSG-AUTH-007';
    case AuthOtpResendCooldown = 'MSG-AUTH-008';
    case AuthOtpTooManyRequests = 'MSG-AUTH-009';
    case AuthVerificationTokenInvalid = 'MSG-AUTH-010';
    case AuthLoginTooManyAttempts = 'MSG-AUTH-011';
    case AuthAlreadySignedIn = 'MSG-AUTH-012';
    case AuthRegistrationInUse = 'MSG-AUTH-013';
    case AuthAccountRestricted = 'MSG-AUTH-014';
    case AuthOtpVerifyInProgress = 'MSG-AUTH-015';
    case AuthRegistrationInProgress = 'MSG-AUTH-016';
    case AuthSessionExpired = 'MSG-AUTH-017';
    case AuthTooManyRequests = 'MSG-AUTH-018';

    case ApiNetworkFailed = 'MSG-API-001';
    case ApiTimeout = 'MSG-API-002';
    case ApiCancelled = 'MSG-API-003';

    case SysUnexpected = 'MSG-SYS-001';

    public function level(): string
    {
        return match ($this) {
            self::AuthAlreadySignedIn => 'warning',
            default => 'error',
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::AuthIncorrectPassword => 'Incorrect password',
            self::AuthAccountLocked => 'Account locked',
            self::AuthOtpFailed => 'OTP verification failed',
            self::AuthPhoneUnsupported => 'This phone number is not supported.',
            self::AuthPhoneRequired => 'Enter your mobile number.',
            self::AuthOtpResendCooldown => 'Please wait before requesting another code.',
            self::AuthOtpTooManyRequests => 'Too many OTP requests. Please try again later.',
            self::AuthVerificationTokenInvalid => 'The verification token is invalid or expired.',
            self::AuthLoginTooManyAttempts => 'Too many login attempts',
            self::AuthAlreadySignedIn => 'This account is already signed in on another device.',
            self::AuthRegistrationInUse => 'The registration details are already in use.',
            self::AuthAccountRestricted => 'This account is restricted and cannot be re-registered.',
            self::AuthOtpVerifyInProgress => 'Verification is already in progress.',
            self::AuthRegistrationInProgress => 'Registration is already in progress.',
            self::AuthSessionExpired => 'Your session has ended. Please sign in again.',
            self::AuthTooManyRequests => 'Too many requests.',
            self::ApiNetworkFailed => 'Network connection failed',
            self::ApiTimeout => 'The request timed out. Try again.',
            self::ApiCancelled => 'The request was cancelled.',
            self::SysUnexpected => 'Unexpected system error',
        };
    }
}
