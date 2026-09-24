<?php

namespace App\Http\Controllers\Support\QuickReplies;

use App\Http\Controllers\Controller;
use App\Models\QuickReply;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class QuickRepliesController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $quickReplies = QuickReply::query()
            ->when($search, function ($query, $search) {
                $query->whereLike('keyword', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Support/quickReplies/Index', [
            'quickReplies' => $quickReplies,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'keyword' => ['required', 'string', 'max:50'],
            'response_en' => ['required', 'string'],
            'response_my' => ['required', 'string'],
            'response_zh' => ['required', 'string'],
        ]);

        QuickReply::create($validated);

        return redirect()
            ->route('support.quick-replies.index')
            ->with('success', 'Quick reply created successfully.');
    }

    public function update(
        Request $request,
        QuickReply $quickReply
    ): RedirectResponse {
        $validated = $request->validate([
            'keyword' => ['required', 'string', 'max:50'],
            'response_en' => ['required', 'string'],
            'response_my' => ['required', 'string'],
            'response_zh' => ['required', 'string'],
        ]);

        $quickReply->update($validated);

        return redirect()
            ->route('support.quick-replies.index')
            ->with('success', 'Quick reply updated successfully.');
    }

    public function destroy(QuickReply $quickReply): RedirectResponse
    {
        $quickReply->delete();

        return redirect()
            ->route('support.quick-replies.index')
            ->with('success', 'Quick reply deleted successfully.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:quick_replies,id'],
        ]);

        QuickReply::whereIn('id', $validated['ids'])->delete();

        return redirect()
            ->route('support.quick-replies.index')
            ->with('success', 'Quick replies deleted successfully.');
    }
}
