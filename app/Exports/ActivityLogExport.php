<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Spatie\Activitylog\Models\Activity;

class ActivityLogExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return ['Actor', 'Action', 'Subject', 'Event', 'Log', 'Occurred At'];
    }

    public function map(mixed $activity): array
    {
        /** @var Activity $activity */
        return [
            $activity->causer?->username ?? 'System',
            $activity->description,
            $this->subject($activity),
            $activity->event ?? '',
            $activity->log_name ?? '',
            $activity->created_at?->toDateTimeString() ?? '',
        ];
    }

    private function subject(Activity $activity): string
    {
        if (!$activity->subject_type || !$activity->subject_id) {
            return '';
        }

        return class_basename($activity->subject_type) . ' #' . $activity->subject_id;
    }
}
