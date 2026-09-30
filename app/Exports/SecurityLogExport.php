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
        return ['Actor', 'Event', 'IP Address', 'User Agent', 'Metadata', 'Occurred At'];
    }

    public function map(mixed $log): array
    {
        /** @var SecurityLog $log */
        $actor = $log->actor;

        return [
            $actor ? $actor->username ?? ($actor->name ?? ($actor->phone ?? 'Unknown')) : 'System',
            $log->event,
            $log->ip_address ?? '',
            $log->user_agent ?? '',
            $log->metadata ? json_encode($log->metadata) : '',
            $log->created_at?->toDateTimeString() ?? '',
        ];
    }
}
