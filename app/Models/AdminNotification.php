<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['type', 'title', 'body', 'severity', 'reference_type', 'reference_id', 'is_read', 'read_at', 'sent_at'])]
class AdminNotification extends Model
{
    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'reference_id' => 'integer',
            'read_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return array{id: int, type: string, title: string, message: string, severity: string, reference_type: string|null, reference_id: int|null, read_at: string|null, created_at: string, category: string, href: string|null}
     */
    public function toDropdownArray(): array
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
            'read_at' => $this->read_at?->toISOString(),
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
