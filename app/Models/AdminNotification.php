<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

#[Fillable(['type', 'title', 'body', 'severity', 'reference_type', 'reference_id', 'sent_at'])]
class AdminNotification extends Model
{
    protected function casts(): array
    {
        return [
            'reference_id' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class, 'admin_notification_recipients')
            ->using(AdminNotificationRecipient::class)
            ->withPivot(['is_read', 'read_at'])
            ->withTimestamps();
    }

    public function readAtFor(Admin $admin): ?Carbon
    {
        $recipient = $this->admins->firstWhere('id', $admin->getKey());
        $readAt = $recipient?->pivot?->read_at;

        return $readAt ? Carbon::parse($readAt) : null;
    }

    /**
     * @return array{id: int, type: string, title: string, message: string, severity: string, reference_type: string|null, reference_id: int|null, read_at: string|null, created_at: string, category: string, href: string|null}
     */
    public function toDropdownArray(?Carbon $readAt = null): array
    {
        // Keep this shared payload in sync for API, page, and broadcast consumers.
        // Add source-request links here when introducing a new reference_type.
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->body,
            'severity' => $this->severity ?? 'normal',
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'read_at' => $readAt?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'category' => $this->type,
            'href' => match ($this->reference_type) {
                'installation_application' => '/service-requests/installations',
                'change_password_request' => '/service-requests/change-password',
                'change_plan_request' => '/service-requests/change-plan',
                'failure_report' => '/service-requests/failures',
                'relocation_request' => '/service-requests/relocations',
                'ledger_health_snapshot_full' => '/reports/ledger-health?type=full&snapshot='.$this->reference_id,
                'ledger_health_snapshot_daily' => '/reports/ledger-health?type=daily&snapshot='.$this->reference_id,
                'ledger_health_snapshot_manual' => '/reports/ledger-health?type=manual&snapshot='.$this->reference_id,
                default => null,
            },
        ];
    }
}
