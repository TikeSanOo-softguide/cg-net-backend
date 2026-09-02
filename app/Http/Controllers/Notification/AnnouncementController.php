<?php

namespace App\Http\Controllers\Notification;

use App\Enums\AnnouncementStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\StoreAnnouncementRequest;
use App\Http\Requests\Notification\UpdateAnnouncementRequest;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = $request->string('status')->toString();
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $sortable = ['content', 'status', 'created_at'];

        if (!in_array($sort, $sortable, true)) {
            $sort = 'created_at';
        }

        $announcement = Announcement::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('content_en', 'like', '%' . $search . '%')
                        ->orWhere('content_zh', 'like', '%' . $search . '%')
                        ->orWhere('content_my', 'like', '%' . $search . '%');
                });
            })
            ->when(
                $status !== '' && in_array($status, array_column(AnnouncementStatus::cases(), 'value'), true),
                function ($query) use ($status): void {
                    match ($status) {
                        'active' => $query->where('is_active', true)->where(function ($query): void {
                            $query->whereNull('end_date')->orWhere('end_date', '>=', now()); // or now('Asia/Tokyo') if needed
                        }),
                        'inactive' => $query->where('is_active', false),
                        'expired' => $query
                            ->where('is_active', true)
                            ->whereNotNull('end_date')
                            ->where('end_date', '<', now()),
                    };
                },
            )
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString()
            ->through(fn(Announcement $announcement) => $this->payload($announcement));

        return Inertia::render('Notification/announcement/Index', [
            'announcement' => $announcement,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function store(StoreAnnouncementRequest $request): RedirectResponse
    {
        $announcement = Announcement::query()->create($request->validated());

        activity('notifications')
            ->causedBy($request->user())
            ->performedOn($announcement)
            ->event('created')
            ->log('announcement_created');

        return redirect()
            ->route('notifications.announcement.index')
            ->with('success', 'notification.announcement.created');
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $announcement->update($request->validated());

        activity('notifications')
            ->causedBy($request->user())
            ->performedOn($announcement)
            ->event('updated')
            ->log('announcement_updated');

        return redirect()
            ->route('notifications.announcement.index')
            ->with('success', 'notification.announcement.updated');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        activity('notifications')
            ->causedBy($request->user())
            ->performedOn($announcement)
            ->event('deleted')
            ->log('announcement_deleted');

        return redirect()
            ->route('notifications.announcement.index')
            ->with('success', 'notification.announcement.deleted');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:admins,id'],
        ])['ids'];

        $deleted = 0;

        foreach (Announcement::query()->whereIn('id', $ids)->get() as $announcement) {
            $announcement->delete();
            activity('announcement')
                ->causedBy($request->user())
                ->performedOn($announcement)
                ->event('deleted')
                ->log('announcement_deleted');
            $deleted++;
        }

        $redirect = redirect()
            ->route('notifications.announcement.index')
            ->with('success', 'common.bulk_deleted')
            ->with('deleted_count', $deleted);

        return $redirect;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Announcement $announcement): array
    {
        return [
            'id' => $announcement->id,
            'content_en' => $announcement->content_en,
            'content_my' => $announcement->content_my,
            'content_zh' => $announcement->content_zh,
            'start_date' => $announcement->start_date,
            'end_date' => $announcement->end_date,
            'is_active' => $announcement->is_active,
            'created_at' => $announcement->created_at,
            'updated_at' => $announcement->updated_at,
        ];
    }
}
