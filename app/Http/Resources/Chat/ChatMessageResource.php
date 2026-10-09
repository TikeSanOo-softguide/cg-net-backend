<?php

namespace App\Http\Resources\Chat;

use App\Enums\ChatSenderType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $language = in_array($request->input('language'), ['en', 'my', 'zh'], true)
            ? $request->input('language')
            : 'en';

        $messageLanguage = $this->language ?? $language;
        $selectedOption = $this->selectedOptionData($messageLanguage);
        $step = $this->stepData($messageLanguage);

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_type' => $this->sender_type?->value ?? $this->sender_type,
            'message' => $this->message,
            'language' => $messageLanguage,
            'attachment_path' => $this->attachment_path,
            'option_id' => $this->option_id,
            'selected_option' => $selectedOption,
            'step' => $step,
            'flow_options' => $step['options'] ?? [],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function flowOptions(Request $request): array
    {
        $language = in_array($request->input('language'), ['en', 'my', 'zh'], true)
            ? $request->input('language')
            : 'en';

        return $this->stepData($this->language ?? $language)['options'] ?? [];
    }

    /**
     * @return array{id: int, name: string|null, message: string|null, options: array<int, array{id: int, label: string}>}|null
     */
    private function stepData(string $language): ?array
    {
        $step = $this->relationLoaded('step')
            ? $this->step
            : $this->step()->with('options')->first();

        if (! $step) {
            $option = $this->relationLoaded('option')
                ? $this->option
                : $this->option()->with(['step.options', 'nextStep.options'])->first();

            $step = $option?->nextStep ?? $option?->step;
        }

        if (! $step) {
            return null;
        }

        $options = $step->options()->where('is_active', true)->orderBy('sort_order')->get();

        return [
            'id' => $step->id,
            'name' => $step->name,
            'message' => $step->{'message_' . $language} ?? $step->message_en,
            'options' => $options
                ->map(fn ($flowOption) => [
                    'id' => $flowOption->id,
                    'label' => $flowOption->{'option_' . $language} ?? $flowOption->option_en,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{id: int, label: string}|null
     */
    private function selectedOptionData(string $language): ?array
    {
        $option = $this->relationLoaded('option')
            ? $this->option
            : $this->option()->with('step')->first();

        if (! $option) {
            return null;
        }

        return [
            'id' => $option->id,
            'label' => $this->sender_type === ChatSenderType::Customer
                ? ($this->message ?? ($option->{'option_' . $language} ?? $option->option_en))
                : ($option->{'option_' . $language} ?? $option->option_en),
        ];
    }
}
