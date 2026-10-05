<?php

namespace App\Http\Controllers\Api\Notification;

use App\Enums\AnnouncementType;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;

class InboxController extends Controller
{
    public function index(): JsonResponse
    {
        $now = now();
        $today = today();

        $announcements = Announcement::query()
            ->where('is_active', true)
            ->where(function ($query) use ($now): void {
                $query->whereNull('start_date')->orWhere('start_date', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('end_date')->orWhere('end_date', '>=', $now);
            })
            ->get()
            ->map(fn (Announcement $announcement): array => [
                'id' => 'announcement-'.$announcement->id,
                'category' => $announcement->type === AnnouncementType::System ? 'system' : 'announcement',
                'title' => [
                    'en' => $announcement->title_en,
                    'my' => $announcement->title_my,
                    'zh' => $announcement->title_zh,
                ],
                'body' => [
                    'en' => $announcement->content_en,
                    'my' => $announcement->content_my,
                    'zh' => $announcement->content_zh,
                ],
                'created_at' => $announcement->created_at?->toIso8601String(),
                'is_read' => false,
            ]);

        $promotions = Promotion::query()
            ->where('is_active', true)
            ->where(function ($query) use ($today): void {
                $query->whereNull('start_date')->orWhereDate('start_date', '<=', $today);
            })
            ->where(function ($query) use ($today): void {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->get()
            ->map(fn (Promotion $promotion): array => [
                'id' => 'promotion-'.$promotion->id,
                'category' => 'promotion',
                'title' => [
                    'en' => $promotion->title_en,
                    'my' => $promotion->title_my,
                    'zh' => $promotion->title_zh,
                ],
                'body' => [
                    'en' => $promotion->description_en,
                    'my' => $promotion->description_my,
                    'zh' => $promotion->description_zh,
                ],
                'created_at' => $promotion->created_at?->toIso8601String(),
                'is_read' => false,
            ]);

        $items = $announcements
            ->concat($promotions)
            ->sortByDesc('created_at')
            ->values();

        return response()->json(['data' => $items]);
    }
}
