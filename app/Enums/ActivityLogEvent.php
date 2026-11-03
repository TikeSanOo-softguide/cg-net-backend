<?php

namespace App\Enums;

enum ActivityLogEvent: string
{
    case AccountBound = 'account_bound';
    case AccountUnbound = 'account_unbound';
    case BulkDeleted = 'bulk_deleted';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Created = 'created';
    case Deleted = 'deleted';
    case Failed = 'failed';
    case LoggedIn = 'logged_in';
    case LoginBlocked = 'login_blocked';
    case LoginFailed = 'login_failed';
    case Pushed = 'pushed';
    case Scheduled = 'scheduled';
    case SessionRevoked = 'session_revoked';
    case StatusChanged = 'status_changed';
    case StatusUpdated = 'status_updated';
    case Succeeded = 'succeeded';
    case Updated = 'updated';
    case Voided = 'voided';
    case WalletAdjusted = 'wallet_adjusted';

    /** @return list<string> */
    public static function values(): array
    {
        $values = array_map(fn(self $event) => $event->value, self::cases());
        sort($values, SORT_STRING);

        return $values;
    }
}
