<?php

namespace App\Http\Controllers\Api\Notification;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Promotion;
use App\Services\Notification\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        app(PushNotificationService::class)->materializeInboxFor($user);
        $visible = $user->appNotifications()->withoutDeletedSources();
        $notifications = $visible->with('templateable')->latest('id')->paginate(30);
        $locale = $user->lang;

        return response()->json([
            'data' => $notifications
                ->getCollection()
                ->map(function (Notification $notification) use ($locale): array {
                    $content = $notification->renderedContent($locale);
                    $payload = [
                        'id' => $notification->id,
                        'title' => $content['title'],
                        'body' => $content['description'],
                        'category' => $notification->category->value,
                        'is_read' => $notification->is_read,
                        'action_type' => $notification->action_type,
                        'action_id' => $notification->action_id,
                        'created_at' => $notification->created_at?->toIso8601String(),
                    ];

                    $source = $notification->templateable;
                    if ($source instanceof Promotion && is_string($source->image_url) && $source->image_url !== '') {
                        $payload['image_url'] = $source->image_url;
                    }

                    return $payload;
                })
                ->values(),
            'unread_count' => $user->unreadAppNotificationCount(),
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
