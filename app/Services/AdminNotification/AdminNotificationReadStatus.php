<?php

namespace App\Services\AdminNotification;

use App\Models\AdminNotification;

class AdminNotificationReadStatus
{
    /**
     * @param  list<int>  $referenceIds
     * @return list<int>
     */
    public function unreadReferenceIds(int $adminId, string $referenceType, array $referenceIds): array
    {
        if ($referenceIds === []) {
            return [];
        }

        return AdminNotification::query()
            ->where('reference_type', $referenceType)
            ->whereIn('reference_id', $referenceIds)
            ->whereHas('admins', fn ($query) => $query
                ->whereKey($adminId)
                ->where('admin_notification_recipients.is_read', false))
            ->pluck('reference_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
