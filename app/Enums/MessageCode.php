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
    case AuthLoggedOut = 'MSG-AUTH-019';

    case ApiNetworkFailed = 'MSG-API-001';
    case ApiTimeout = 'MSG-API-002';
    case ApiCancelled = 'MSG-API-003';

    case SysUnexpected = 'MSG-SYS-001';
    case SysValidation = 'MSG-SYS-002';
    case SysForbidden = 'MSG-SYS-003';
    case SysNotFound = 'MSG-SYS-004';

    case BbConnectionFailed = 'MSG-BB-001';
    case BbNotMatch = 'MSG-BB-002';
    case BbAlreadyConnected = 'MSG-BB-003';
    case BbDifferentAccount = 'MSG-BB-004';
    case BbInUse = 'MSG-BB-005';
    case BbConnected = 'MSG-BB-006';
    case BbDisconnected = 'MSG-BB-007';
    case BbFound = 'MSG-BB-008';
    case BbUpstreamFailed = 'MSG-BB-009';

    case TopupValid = 'MSG-TOPUP-001';
    case TopupAlreadyUsed = 'MSG-TOPUP-002';
    case TopupInvalid = 'MSG-TOPUP-003';
    case TopupAccountNotFound = 'MSG-TOPUP-004';
    case TopupAccountUnavailable = 'MSG-TOPUP-005';
    case TopupWalletNotFound = 'MSG-TOPUP-006';
    case TopupWalletInactive = 'MSG-TOPUP-007';
    case TopupCardInvalid = 'MSG-TOPUP-008';
    case TopupActionIncomplete = 'MSG-TOPUP-009';
    case TopupSuccess = 'MSG-TOPUP-010';
    case TopupRequestIncomplete = 'MSG-TOPUP-011';
    case TopupTooManyUser = 'MSG-TOPUP-012';
    case TopupTooManyIp = 'MSG-TOPUP-013';
    case TopupAlreadyProcessed = 'MSG-TOPUP-014';
    case TopupSerialThrottled = 'MSG-TOPUP-015';

    case PkgWalletNotFound = 'MSG-PKG-001';
    case PkgWalletInactive = 'MSG-PKG-002';
    case PkgCatalogUnavailable = 'MSG-PKG-003';
    case PkgCompletionPending = 'MSG-PKG-004';
    case PkgPurchased = 'MSG-PKG-005';
    case PkgAlreadyProcessed = 'MSG-PKG-006';
    case PkgActivationRefunded = 'MSG-PKG-007';
    case PkgProcessing = 'MSG-PKG-008';
    case PkgReconciliationPending = 'MSG-PKG-009';
    case PkgInsufficientBalance = 'MSG-PKG-010';
    case PkgKeyUsed = 'MSG-PKG-011';
    case PkgKeyMismatch = 'MSG-PKG-012';
    case PkgCannotReplay = 'MSG-PKG-013';

    case PwdChanged = 'MSG-PWD-001';
    case PwdChangeExpired = 'MSG-PWD-002';

    case DevRegistered = 'MSG-DEV-001';
    case DevRemoved = 'MSG-DEV-002';

    case UserPasswordIncorrect = 'MSG-USER-002';
    case UserDeactivated = 'MSG-USER-003';
    case UserSuspended = 'MSG-USER-004';

    case FtthWalletNotFound = 'MSG-FTTH-001';
    case FtthWalletInactive = 'MSG-FTTH-002';
    case FtthInsufficientBalance = 'MSG-FTTH-003';
    case FtthAccountMissing = 'MSG-FTTH-004';
    case FtthBillingFailed = 'MSG-FTTH-005';
    case FtthPendingSlip = 'MSG-FTTH-006';
    case FtthPaidSlips = 'MSG-FTTH-007';
    case FtthPaid = 'MSG-FTTH-008';
    case FtthRefunded = 'MSG-FTTH-009';
    case FtthProcessing = 'MSG-FTTH-010';
    case FtthAlreadyProcessed = 'MSG-FTTH-011';
    case FtthAlreadyProcessing = 'MSG-FTTH-012';
    case FtthCannotReplay = 'MSG-FTTH-013';

    case ChatStaleOption = 'MSG-CHAT-001';
    case ChatInvalidStart = 'MSG-CHAT-002';
    case ChatNoActive = 'MSG-CHAT-003';
    case ChatOptionMissing = 'MSG-CHAT-004';
    case ChatOptionInactive = 'MSG-CHAT-005';
    case ChatNoNextStep = 'MSG-CHAT-006';
    case ChatNotFound = 'MSG-CHAT-007';
    case ChatLanguageInvalid = 'MSG-CHAT-008';
    case ChatCustomersOnly = 'MSG-CHAT-009';
    case ChatForbidden = 'MSG-CHAT-010';
    case ChatWaitingForAgent = 'MSG-CHAT-011';
    case ChatLive = 'MSG-CHAT-012';
    case ChatClosed = 'MSG-CHAT-013';
    case ChatSelectOption = 'MSG-CHAT-014';
    case ChatWaitForJoin = 'MSG-CHAT-015';

    public function level(): string
    {
        return match ($this) {
            self::AuthAlreadySignedIn,
            self::BbAlreadyConnected,
            self::TopupAlreadyUsed,
            self::TopupAlreadyProcessed,
            self::PkgCompletionPending,
            self::PkgAlreadyProcessed,
            self::PkgProcessing,
            self::PkgReconciliationPending,
            self::FtthProcessing,
            self::FtthAlreadyProcessed,
            self::FtthAlreadyProcessing,
            self::ChatWaitingForAgent,
            self::ChatLive,
            self::ChatClosed,
            self::ChatSelectOption,
            self::ChatWaitForJoin
                => 'warning',
            self::AuthLoggedOut,
            self::BbConnected,
            self::BbDisconnected,
            self::BbFound,
            self::TopupValid,
            self::TopupSuccess,
            self::PkgPurchased,
            self::PwdChanged,
            self::DevRegistered,
            self::DevRemoved,
            self::UserDeactivated,
            self::FtthPendingSlip,
            self::FtthPaidSlips,
            self::FtthPaid
                => 'success',
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
            self::SysValidation => 'The given data was invalid.',
            self::SysForbidden => 'This action is unauthorized.',
            self::SysNotFound => 'The requested resource was not found.',
            self::AuthLoggedOut => 'Logged out successfully.',
            self::BbConnectionFailed => 'broadband_api_connection_failed',
            self::BbNotMatch => 'broadband_account_not_match',
            self::BbAlreadyConnected => 'broadband_already_connected',
            self::BbDifferentAccount => 'broadband_different_account',
            self::BbInUse => 'broadband_account_in_use',
            self::BbConnected => 'Successfully connected',
            self::BbDisconnected => 'Successfully disconnected',
            self::BbFound => 'Account found successfully',
            self::BbUpstreamFailed => 'broadband_api_connection_failed',
            self::TopupValid => 'This top-up card is valid.',
            self::TopupAlreadyUsed => 'This top-up card has already been used.',
            self::TopupInvalid => 'Invalid top-up card.',
            self::TopupAccountNotFound => 'Account not found.',
            self::TopupAccountUnavailable => 'Account is not available.',
            self::TopupWalletNotFound => 'Wallet not found.',
            self::TopupWalletInactive => 'Wallet is not active.',
            self::TopupCardInvalid => 'Invalid or unavailable top-up card.',
            self::TopupActionIncomplete => 'This action could not be completed.',
            self::TopupSuccess => 'Top-up successful.',
            self::TopupRequestIncomplete => 'This request could not be completed.',
            self::TopupTooManyUser => 'Too many attempts, retry after 30 minutes.',
            self::TopupTooManyIp => 'Too many attempts, retry after 1 hour.',
            self::TopupAlreadyProcessed => 'Top-up already processed.',
            self::TopupSerialThrottled => 'Too many attempts. Please try again after 30 minutes.',
            self::PkgWalletNotFound => 'Wallet not found.',
            self::PkgWalletInactive => 'Wallet is not active.',
            self::PkgCatalogUnavailable => 'Package catalog is temporarily unavailable. No payment was taken.',
            self::PkgCompletionPending => 'Activation is confirmed and purchase completion is pending reconciliation.',
            self::PkgPurchased => 'Package purchased successfully.',
            self::PkgAlreadyProcessed => 'Package purchase already processed.',
            self::PkgActivationRefunded => 'Package activation failed. Wallet was refunded.',
            self::PkgProcessing => 'This package purchase is already being processed.',
            self::PkgReconciliationPending
                => 'Package activation is awaiting provider reconciliation. The wallet debit remains on hold.',
            self::PkgInsufficientBalance => 'Insufficient wallet balance.',
            self::PkgKeyUsed => 'This idempotency key was already used.',
            self::PkgKeyMismatch => 'This idempotency key was already used for a different package.',
            self::PkgCannotReplay => 'This package purchase cannot be replayed.',
            self::PwdChanged => 'Password changed successfully.',
            self::PwdChangeExpired => 'The password change request is invalid or expired.',
            self::DevRegistered => 'Device token registered.',
            self::DevRemoved => 'Device token removed.',
            self::UserPasswordIncorrect => 'The provided password is incorrect.',
            self::UserDeactivated => 'Account deactivated successfully.',
            self::UserSuspended => 'Account suspended successfully.',
            self::FtthWalletNotFound => 'Wallet not found.',
            self::FtthWalletInactive => 'Wallet is not active.',
            self::FtthInsufficientBalance => 'Insufficient wallet balance.',
            self::FtthAccountMissing => 'Broadband account number not found.',
            self::FtthBillingFailed => 'Billing server request failed.',
            self::FtthPendingSlip => 'Pending FTTH bill slip retrieved.',
            self::FtthPaidSlips => 'Paid FTTH bill slips retrieved.',
            self::FtthPaid => 'FTTH bill paid successfully.',
            self::FtthRefunded => 'External billing payment failed. Wallet was refunded.',
            self::FtthProcessing => 'FTTH bill payment is processing. Confirmation is pending from the billing server.',
            self::FtthAlreadyProcessed => 'FTTH bill payment already processed.',
            self::FtthAlreadyProcessing => 'This payment request is already being processed.',
            self::FtthCannotReplay => 'This payment request cannot be replayed.',
            self::ChatStaleOption => 'This option is no longer available. The current conversation has been refreshed.',
            self::ChatInvalidStart => 'The chat flow start step is not configured correctly.',
            self::ChatNoActive => 'No active conversation. Please start a new conversation.',
            self::ChatOptionMissing => 'Chat flow option not found.',
            self::ChatOptionInactive => 'This option is no longer active.',
            self::ChatNoNextStep => 'The selected option does not have a valid next step.',
            self::ChatNotFound => 'Conversation not found.',
            self::ChatLanguageInvalid => 'The language must be one of: en, my, zh.',
            self::ChatCustomersOnly => 'Only customers can use the chat API.',
            self::ChatForbidden => 'You do not have access to this conversation.',
            self::ChatWaitingForAgent
                => 'Conversation is waiting for an agent. Chat flow options are no longer available.',
            self::ChatLive => 'Conversation is in live chat. Chat flow options are no longer available.',
            self::ChatClosed => 'Conversation is closed. Please start a new conversation.',
            self::ChatSelectOption => 'Please select a chat flow option. Free-text messages are not available yet.',
            self::ChatWaitForJoin => 'Please wait for an agent to join the conversation.',
        };
    }
}
