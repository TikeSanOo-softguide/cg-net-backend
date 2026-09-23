<?php

namespace App\Http\Controllers\Package;

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
            'months' => ['required', 'integer', 'min:1', 'max:999', Rule::unique('terms', 'months')->withoutTrashed()],
        ]);

        Term::query()->create($data);

        return redirect()->route('packages.index')->with('success', 'packages.terms.created');
    }

    public function update(Request $request, Term $term): RedirectResponse
    {
        $data = $request->validate([
            'months' => [
                'required',
                'integer',
                'min:1',
                'max:999',
                Rule::unique('terms', 'months')->ignore($term->id)->withoutTrashed(),
            ],
        ]);

        $term->update($data);

        return redirect()->route('packages.index')->with('success', 'packages.terms.updated');
    }

    public function destroy(Term $term): RedirectResponse
    {
        if ($term->packages()->exists()) {
            return back()->withErrors([
                'delete' => __('packages.terms.cannot_delete_has_package'),
            ]);
        }

        $term->delete();

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
        return redirect()->route('packages.index')->with('success', 'packages.terms.bulk_deleted');
    }
}
