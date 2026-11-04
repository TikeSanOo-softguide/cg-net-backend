<?php

namespace App\Models;

use App\Enums\NotificationActionType;
use App\Enums\NotificationCategory;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

#[Fillable([
    'user_id',
    'category',
    'is_read',
    'sent_at',
    'action_type',
    'action_id',
    'templateable_type',
    'templateable_id',
    'template_data',
])]
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'category' => NotificationCategory::class,
            'is_read' => 'boolean',
            'sent_at' => 'datetime',
            'template_data' => 'array',
        ];
    }

    public function templateable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Campaign copies stay after an announcement or promotion is deleted.
     * Hide those copies so the inbox matches the current CMS records.
     */
    public function scopeWithoutDeletedSources(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where(function (Builder $query): void {
                $query->where('action_type', NotificationActionType::Announcement->value)
                    ->whereExists(fn ($exists) => $this->whereSourceExists($exists, 'announcements'));
            })->orWhere(function (Builder $query): void {
                $query->where('action_type', NotificationActionType::Promotion->value)
                    ->whereExists(fn ($exists) => $this->whereSourceExists($exists, 'promotions'));
            })->orWhere(function (Builder $query): void {
                $query->whereNull('action_type')
                    ->orWhereNotIn('action_type', [
                        NotificationActionType::Announcement->value,
                        NotificationActionType::Promotion->value,
                    ]);
            });
        });
    }

    private function whereSourceExists($exists, string $table): void
    {
        $id = match ($exists->getConnection()->getDriverName()) {
            'pgsql' => "{$table}.id::text",
            'sqlite' => "CAST({$table}.id AS TEXT)",
            default => "CAST({$table}.id AS CHAR)",
        };

        $exists->selectRaw('1')
            ->from($table)
            ->whereRaw("{$id} = notifications.action_id")
            ->whereNull("{$table}.deleted_at");
    }

    /**
     * @return array{title: string, description: string}
     */
    public function renderedContent(string $locale): array
    {
        $templateData = $this->template_data ?? [];

        if (isset($templateData['title_en'], $templateData['body_en'])) {
            $language = in_array($locale, ['en', 'my', 'zh'], true) ? $locale : 'en';

            return [
                'title' => $templateData["title_{$language}"] ?? $templateData['title_en'],
                'description' => $templateData["body_{$language}"] ?? $templateData['body_en'],
            ];
        }

        $template = $this->templateable;
        if ($template instanceof NotificationTemplate) {
            return $template->render($locale, $templateData);
        }

        throw new LogicException("Notification {$this->id} has no valid content template.");
    }
}
