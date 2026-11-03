<?php

namespace App\Support;

use App\Enums\ActivityLogEvent;

use App\Enums\ActivityLogName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class CmsBulkDelete
{
    /**
     * @param  Builder<Model>  $query
     * @param  (callable(Model): ?string)|null  $deletionError
     * @param  (callable(Model): void)|null  $beforeDelete
     * @param  (callable(Model): void)|null  $afterDelete
     */
    public static function run(
        Request $request,
        Builder $query,
        string $indexRoute,
        string $logEvent,
        ActivityLogName $activityLogName,
        ?callable $deletionError = null,
        ?callable $beforeDelete = null,
        ?callable $afterDelete = null,
    ): RedirectResponse {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', Rule::exists($query->getModel()->getTable(), 'id')],
        ])['ids'];

        $deleted = 0;
        $skippedReason = null;

        foreach ((clone $query)->whereIn('id', $ids)->get() as $item) {
            $error = $deletionError ? $deletionError($item) : null;

            if ($error !== null) {
                $skippedReason ??= $error;

                continue;
            }

            if ($beforeDelete) {
                $beforeDelete($item);
            }

            DB::transaction(function () use ($item, $logEvent, $request, $activityLogName): void {
                $item->delete();
                activity($activityLogName->value)->causedBy($request->user())->performedOn($item)->event(ActivityLogEvent::Deleted->value)->log($logEvent);
            });

            if ($afterDelete) {
                $afterDelete($item);
            }

            $deleted++;
        }

        if ($deleted === 0) {
            return back()->withErrors(['delete' => $skippedReason ?? 'common.bulk_delete_failed']);
        }

        $redirect = redirect()
            ->route($indexRoute)
            ->with('success', 'common.bulk_deleted')
            ->with('deleted_count', $deleted);

        if ($skippedReason !== null) {
            return $redirect->withErrors(['delete' => 'cms.bulk_delete_partial']);
        }

        return $redirect;
    }
}
