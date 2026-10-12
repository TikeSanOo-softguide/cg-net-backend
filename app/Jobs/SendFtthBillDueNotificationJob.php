<?php

namespace App\Jobs;

use App\Enums\NotificationActionType;
use App\Enums\NotificationCategory;
use App\Enums\NotificationTemplateType;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Notifications\FtthBillDueNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class SendFtthBillDueNotificationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $userId,
        public readonly string $accountNumber,
        public readonly string $dueDate,
        public readonly string $actionId,
    ) {
        $this->onQueue('notifications');
    }

    public function uniqueId(): string
    {
        return "ftth-bill-due:{$this->userId}:{$this->actionId}";
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $template = NotificationTemplate::query()
            ->where('type', NotificationTemplateType::BillAlert->value)
            ->firstOrFail();

        $templateData = [
            'account_number' => $this->accountNumber,
            'due_date' => $this->dueDate,
        ];

        $notification = Notification::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'action_type' => NotificationActionType::FtthBill->value,
                'action_id' => $this->actionId,
            ],
            [
                'category' => NotificationCategory::Announcement,
                'templateable_type' => NotificationTemplate::class,
                'templateable_id' => $template->id,
                'template_data' => $templateData,
            ],
        );

        if ($notification->sent_at !== null || !$user->deviceTokens()->exists()) {
            return;
        }

        $localized = $template->render($user->lang, $templateData);

        NotificationFacade::sendNow(
            $user,
            new FtthBillDueNotification(
                title: $localized['title'],
                body: $localized['description'],
                actionId: $this->actionId,
            ),
        );

        $notification->update(['sent_at' => now()]);
    }
}
