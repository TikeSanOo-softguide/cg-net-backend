<?php

namespace App\Models;

use App\Enums\NotificationTemplateType;
use Carbon\CarbonImmutable;
use Database\Factories\NotificationTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use InvalidArgumentException;

#[Fillable([
    'type',
    'title_en',
    'title_my',
    'title_zh',
    'description_en',
    'description_my',
    'description_zh',
])]
class NotificationTemplate extends Model
{
    /** @use HasFactory<NotificationTemplateFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => NotificationTemplateType::class,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{title: string, description: string}
     */
    public function render(string $locale, array $data = []): array
    {
        $locale = in_array($locale, ['en', 'my', 'zh'], true) ? $locale : 'en';
        $title = $this->{"title_{$locale}"} ?: $this->title_en;
        $description = $this->{"description_{$locale}"} ?: $this->description_en;

        if ($this->type === NotificationTemplateType::BillAlert && is_string($data['endtime'] ?? null)) {
            foreach (['Y-m-d H:i', 'Y-m-d H:i:s'] as $format) {
                try {
                    $endTime = CarbonImmutable::createFromFormat('!' . $format, $data['endtime']);
                } catch (InvalidArgumentException) {
                    continue;
                }

                if ($endTime !== false && $endTime->format($format) === $data['endtime']) {
                    $data['endtime'] = $endTime->format('F j, Y g:i A');
                    break;
                }
            }
        }

        $replacements = [];

        foreach ($data as $key => $value) {
            if (is_string($value) || is_int($value) || is_float($value)) {
                $replacements['{'.$key.'}'] = (string) $value;
            }
        }

        return [
            'title' => strtr($title, $replacements),
            'description' => strtr($description, $replacements),
        ];
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'templateable');
    }
}
