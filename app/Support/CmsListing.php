<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class CmsListing
{
    /**
     * @param  list<string>  $searchColumns
     * @param  list<string>  $sortable
     * @return array{paginator: LengthAwarePaginator, filters: array{search: string, status: string, sort: string, direction: string}}
     */
    public static function paginate(
        Request $request,
        Builder $query,
        array $searchColumns,
        array $sortable,
        string $defaultSort = 'created_at',
        ?string $statusColumn = null,
    ): array {
        $search = trim((string) $request->string('search'));
        $status = $request->string('status')->toString();
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, $sortable, true)) {
            $sort = $defaultSort;
        }

        $query
            ->when($search !== '' && $searchColumns !== [], function (Builder $query) use ($search, $searchColumns): void {
                $query->where(function (Builder $query) use ($search, $searchColumns): void {
                    foreach ($searchColumns as $index => $column) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $query->{$method}($column, 'like', '%'.$search.'%');
                    }
                });
            })
            ->when($status !== '' && $statusColumn !== null, function (Builder $query) use ($status, $statusColumn): void {
                if ($statusColumn === 'is_active') {
                    if (in_array($status, ['active', 'inactive'], true)) {
                        $query->where('is_active', $status === 'active');
                    }

                    return;
                }

                $query->where($statusColumn, $status);
            })
            ->orderBy($sort, $direction);

        return [
            'paginator' => $query->paginate(15)->withQueryString(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ];
    }
}
