<?php

namespace App\Http\Controllers\Package;

use App\Http\Controllers\Controller;
use App\Models\Term;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TermController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'months' => ['required', 'integer', 'min:1'],
        ]);

        Term::query()->create($data);

        return redirect()->route('packages.index')->with('success', 'packages.terms.created');
    }

    public function update(Request $request, Term $term): RedirectResponse
    {
        $data = $request->validate([
            'months' => ['required', 'integer', 'min:1'],
        ]);

        $term->update($data);

        return redirect()->route('packages.index')->with('success', 'packages.terms.updated');
    }

    public function destroy(Term $term): RedirectResponse
    {
        $term->delete();

        return redirect()->route('packages.index')->with('success', 'packages.terms.deleted');
    }
}
