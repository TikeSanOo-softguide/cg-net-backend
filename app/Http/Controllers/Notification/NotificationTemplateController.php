<?php

namespace App\Http\Controllers\Notification;

use App\Enums\NotificationTemplateType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\UpdateNotificationTemplateRequest;
use App\Models\NotificationTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()?->can('notifications.view') || $request->user()?->can('notifications.update'),
            403,
        );

        return Inertia::render('Notification/Templates/Index', [
            'templates' => NotificationTemplate::query()
                ->orderBy('id')
                ->get()
                ->map(fn (NotificationTemplate $template): array => $this->payload($template))
                ->values(),
        ]);
    }

    public function update(
        UpdateNotificationTemplateRequest $request,
        NotificationTemplate $notificationTemplate,
    ): RedirectResponse {
        abort_unless($notificationTemplate->type instanceof NotificationTemplateType, 404);
        $notificationTemplate->update($request->validated());

        activity('notifications')
            ->causedBy($request->user())
            ->performedOn($notificationTemplate)
            ->event('updated')
            ->log('notification_template_updated');

        return redirect()
            ->route('notifications.templates.index')
            ->with('success', 'notification.template.updated');
    }

    /**
     * @return array<string, int|string>
     */
    private function payload(NotificationTemplate $template): array
    {
        return [
            'id' => $template->id,
            'type' => $template->type->value,
            'title_en' => $template->title_en,
            'title_my' => $template->title_my,
            'title_zh' => $template->title_zh,
            'description_en' => $template->description_en,
            'description_my' => $template->description_my,
            'description_zh' => $template->description_zh,
        ];
    }
}
