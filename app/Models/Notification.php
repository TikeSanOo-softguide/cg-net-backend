<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
