<?php

namespace App\Exports;

use App\Models\SecurityLog;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SecurityLogExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return ['Actor', 'Device name', 'Event', 'IP Address', 'User Agent', 'Metadata', 'Occurred At'];
    }

    public function map(mixed $log): array
    {
        /** @var SecurityLog $log */
        $actor = $log->actor;
        $metadata = $log->metadata ?? [];

        return [
            $actor ? $actor->username ?? ($actor->name ?? ($actor->phone ?? 'Unknown')) : 'System',
            is_string($metadata['device_name'] ?? null) ? $metadata['device_name'] : '',
            $log->event,
            $log->ip_address ?? '',
            $log->user_agent ?? '',
            json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '',
            $log->created_at?->toDateTimeString() ?? '',
        ];
    }
}
