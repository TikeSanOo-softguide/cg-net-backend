<?php

namespace App\Http\Controllers\Api\Notification;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $user->appNotifications()
            ->with('templateable')
            ->latest('id')
            ->paginate(30);
        $locale = $user->lang;

        return response()->json([
            'data' => $notifications->getCollection()->map(function (Notification $notification) use ($locale): array {
                $template = $notification->templateable;

                if (! $template instanceof NotificationTemplate) {
                    throw new LogicException("Notification {$notification->id} has no valid content template.");
                }

                $content = $template->render($locale, $notification->template_data ?? []);

                return [
                    'id' => $notification->id,
                    'title' => $content['title'],
                    'body' => $content['description'],
                    'category' => $notification->category->value,
                    'is_read' => $notification->is_read,
                    'action_type' => $notification->action_type,
                    'action_id' => $notification->action_id,
                    'created_at' => $notification->created_at?->toIso8601String(),
                ];
            })->values(),
            'unread_count' => $user->appNotifications()->where('is_read', false)->count(),
            'current_page' => $notifications->currentPage(),
            'last_page' => $notifications->lastPage(),
        ]);
    }

    public function markAsRead(Request $request, int $notificationId): JsonResponse
    {
        $notification = $request->user()->appNotifications()->findOrFail($notificationId);
        $notification->update(['is_read' => true]);

        return response()->json([
            'data' => [
                'id' => $notification->id,
                'is_read' => true,
            ],
        ]);
    }
}
