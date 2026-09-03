<?php

namespace App\Http\Controllers\Api\Notification;

use App\Http\Resources\Notification\AnnouncementResource;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResource
    {
        $now = now();

        $announcements = Announcement::query()
            ->where('is_active', true)
            ->where(function ($query) use ($now): void {
                $query->whereNull('start_date')->orWhere('start_date', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('end_date')->orWhere('end_date', '>=', $now);
            })
            ->orderByDesc('start_date')
            ->latest()
            ->get();

        return AnnouncementResource::collection($announcements);
    }
}
