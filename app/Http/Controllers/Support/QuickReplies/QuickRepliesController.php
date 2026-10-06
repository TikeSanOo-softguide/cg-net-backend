<?php

namespace App\Http\Controllers\Support\QuickReplies;

use App\Enums\QuickReplyCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\QuickReplyRequest;
use App\Models\QuickReply;
use App\Support\CmsListing;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QuickRepliesController extends Controller
{
    public function index(Request $request): Response
    {
        $category = $request->string('category')->toString();

        if (QuickReplyCategory::tryFrom($category) === null) {
            $category = '';
        }

        $query = QuickReply::query()->when(
            $category !== '',
            fn($builder) => $builder->where('category', $category),
        );

        $listing = CmsListing::paginate(
            $request,
            $query,
            ['keyword', 'response_en', 'response_my', 'response_zh'],
            ['keyword', 'category', 'is_active', 'created_at', 'updated_at'],
            'updated_at',
        );

        return Inertia::render('Support/quickReplies/Index', [
            'items' => $listing['paginator']->through(fn(QuickReply $reply) => $this->payload($reply)),
            'existingKeywords' => QuickReply::query()->pluck('keyword')->all(),
            'filters' => [
                ...$listing['filters'],
                'category' => $category,
            ],
            'categories' => array_map(
                fn(QuickReplyCategory $category) => [
                    'value' => $category->value,
                    'label_key' => 'support.quick_replies.categories.' . $category->value,
                ],
                QuickReplyCategory::cases(),
            ),
        ]);
    }

    public function store(QuickReplyRequest $request): RedirectResponse
    {
        $this->saveReply(new QuickReply, $request->validated());

        return redirect()
            ->route('support.quick-replies.index')
            ->with('success', 'support.quick_replies.created');
    }

    public function update(QuickReplyRequest $request, QuickReply $quickReply): RedirectResponse
    {
        $this->saveReply($quickReply, $request->validated());

        return redirect()
            ->route('support.quick-replies.index')
            ->with('success', 'support.quick_replies.updated');
    }

    public function destroy(QuickReply $quickReply): RedirectResponse
    {
        $quickReply->delete();

        return redirect()
            ->route('support.quick-replies.index')
            ->with('success', 'support.quick_replies.deleted');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:quick_replies,id'],
        ]);

        $deleted = QuickReply::query()->whereIn('id', $validated['ids'])->delete();

        if ($deleted === 0) {
            return back()->withErrors(['delete' => 'support.quick_replies.bulk_delete_failed']);
        }

        return redirect()
            ->route('support.quick-replies.index')
            ->with('success', 'support.quick_replies.bulk_deleted')
            ->with('deleted_count', $deleted);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function saveReply(QuickReply $reply, array $attributes): void
    {
        try {
            $reply->fill($attributes)->save();
        } catch (QueryException $exception) {
            if (! $this->isKeywordConflict($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'keyword' => __('support.quick_replies.validation.keyword_unique'),
            ]);
        }
    }

    private function isKeywordConflict(QueryException $exception): bool
    {
        $sqlState = (string) $exception->getCode();

        return in_array($sqlState, ['23000', '23505'], true)
            && str_contains(strtolower($exception->getMessage()), 'keyword');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(QuickReply $reply): array
    {
        return [
            'id' => $reply->id,
            'keyword' => $reply->keyword,
            'category' => $reply->category->value,
            'response_en' => $reply->response_en,
            'response_my' => $reply->response_my,
            'response_zh' => $reply->response_zh,
            'created_at' => $reply->created_at,
            'updated_at' => $reply->updated_at,
        ];
    }
}
