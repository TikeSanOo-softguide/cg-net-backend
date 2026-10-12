<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class AdminNotificationRecipient extends Pivot
{
    protected $table = 'admin_notification_recipients';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }
}
