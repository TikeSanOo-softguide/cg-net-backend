<?php

namespace App\Http\Controllers\Package;

use App\Enums\ActivityLogEvent;

use App\Enums\ActivityLogName;
use App\Http\Controllers\Controller;
use App\Models\Term;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TermController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'months' => [
                'required',
                'integer',
                'min:1',
                'max:999999999',
                Rule::unique('terms', 'months')->withoutTrashed(),
            ],
        ]);

        $term = Term::query()->create($data);

        activity(ActivityLogName::Term->value)->causedBy($request->user())->performedOn($term)->event(ActivityLogEvent::Created->value)->log('term_created');

        return redirect()->route('packages.index')->with('success', 'packages.terms.created');
    }

    public function update(Request $request, Term $term): RedirectResponse
    {
        $data = $request->validate([
            'months' => [
                'required',
                'integer',
                'min:1',
                'max:999999999',
                Rule::unique('terms', 'months')->ignore($term->id)->withoutTrashed(),
            ],
        ]);

        $term->update($data);

        activity(ActivityLogName::Term->value)->causedBy($request->user())->performedOn($term)->event(ActivityLogEvent::Updated->value)->log('term_updated');

        return redirect()->route('packages.index')->with('success', 'packages.terms.updated');
    }

    public function destroy(Request $request, Term $term): RedirectResponse
    {
        if ($term->packages()->exists()) {
            return back()->withErrors([
                'delete' => __('packages.terms.cannot_delete_has_package'),
            ]);
        }

        $term->delete();

        activity(ActivityLogName::Term->value)->causedBy($request->user())->performedOn($term)->event(ActivityLogEvent::Deleted->value)->log('term_deleted');

        return redirect()->route('packages.index')->with('success', 'packages.terms.deleted');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:terms,id'],
        ])['ids'];

        $deletableIds = Term::query()->whereIn('id', $ids)->whereDoesntHave('packages')->pluck('id');
        $deletedCount = Term::query()->whereIn('id', $deletableIds)->delete();

        if ($deletedCount === 0) {
            return back()->with('error', __('common.bulk_delete_failed'));
        }

        activity(ActivityLogName::Term->value)
            ->causedBy($request->user())
            ->event(ActivityLogEvent::BulkDeleted->value)
            ->withProperties(['ids' => $deletableIds])
            ->log('terms_bulk_deleted');

        return redirect()->route('packages.index')->with('success', 'packages.terms.bulk_deleted');
    }
}
