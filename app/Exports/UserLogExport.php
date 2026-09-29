<?php

namespace App\Exports;

use App\Models\UserLog;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UserLogExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return ['User', 'Email', 'IP Address', 'User Agent', 'Action', 'Metadata', 'Occurred At'];
    }

    public function map(mixed $log): array
    {
        /** @var UserLog $log */
        $metadata = $log->metadata ?? [];
        $action = $metadata['action'] ?? '';

        return [
            $log->user?->name ?? 'Unknown',
            $log->user?->email ?? '',
            $log->ip_address ?? '',
            $log->user_agent ?? '',
            is_scalar($action) ? (string) $action : '',
            json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '',
            $log->created_at?->toDateTimeString() ?? '',
        ];
    }
}
