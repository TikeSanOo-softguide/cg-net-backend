<?php

namespace App\Http\Controllers\Api\ChatFlow;

use App\Enums\ChatConversationStatus;
use App\Enums\ChatFlowOptionAction;
use App\Enums\ChatSenderType;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatFlowOption;
use App\Models\ChatFlowStep;
use App\Services\Telegram\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ChatFlowController extends Controller
{
    public function __construct(private readonly TelegramService $telegram) {}

    public function start(Request $request)
    {
        $language = $this->resolveLanguage($request->input('language'));

        $conversation = $this->resolveCustomerConversation($request->user());
        $startStep = ChatFlowStep::query()->where('is_start', true)->first();

        if (! $startStep) {
            throw new HttpException(404, 'No chat flow start step has been configured.');
        }

        if (! $conversation) {
            $conversation = $request->user()->chatConversations()->create([
                'current_step_id' => $startStep->id,
                'status' => ChatConversationStatus::Open,
            ]);
        } else {
            $conversation->update([
                'current_step_id' => $conversation->current_step_id ?? $startStep->id,
                'status' => $conversation->status ?? ChatConversationStatus::Open,
            ]);
        }

        $conversation->loadMissing('currentStep.options');
        $step = $conversation->currentStep ?? $startStep;

        return response()->json([
            'success' => true,
            'data' => [
                'conversation_id' => $conversation->id,
                'step' => $this->buildStepPayload($step, $language),
            ],
        ]);
    }

    public function current(Request $request)
    {
        $language = $this->resolveLanguage($request->input('language'));
        $conversation = $this->resolveCustomerConversation($request->user(), $request->input('conversation_id'));

        if (! $conversation) {
            return $this->start($request);
        }

        $step = $conversation->currentStep ?: $this->getStartStep();

        return response()->json([
            'success' => true,
            'data' => [
                'conversation_id' => $conversation->id,
                'status' => $conversation->status->value,
                'step' => $this->buildStepPayload($step, $language),
            ],
        ]);
    }

    public function selectOption(Request $request)
    {
        $language = $this->resolveLanguage($request->input('language'));
        $optionId = (int) $request->input('option_id');

        $conversation = $this->resolveCustomerConversation($request->user(), $request->input('conversation_id'));

        if (! $conversation) {
            $conversation = $this->resolveCustomerConversation($request->user());
            if (! $conversation) {
                $conversation = $request->user()->chatConversations()->create([
                    'current_step_id' => $this->getStartStep()->id,
                    'status' => ChatConversationStatus::Open,
                ]);
            }
        }

        $option = ChatFlowOption::query()->with(['step', 'nextStep'])->find($optionId);

        if (! $option) {
            abort(404, 'Chat flow option not found.');
        }

        if (! $option->is_active) {
            abort(422, 'This option is no longer active.');
        }

        if ($option->step_id !== $conversation->current_step_id) {
            abort(422, 'This option does not belong to the customer\'s current flow state.');
        }

        $selectedLabel = $this->localizedValue($option, 'option_' . $language);
        $transitionToWaiting = false;

        $payload = DB::transaction(function () use ($conversation, $language, $option, $selectedLabel, &$transitionToWaiting) {
            $previousStatus = $conversation->status;
            $conversation->refresh();

            $conversation->messages()->create([
                'sender_type' => ChatSenderType::Customer->value,
                'message' => $selectedLabel,
                'option_id' => $option->id,
                'is_read' => true,
            ]);

            $result = match ($option->action) {
                ChatFlowOptionAction::GoToStep->value => $this->handleGoToStep($conversation, $option, $language),
                ChatFlowOptionAction::GoToUrl->value => $this->handleGoToUrl($conversation, $option, $language),
                ChatFlowOptionAction::ReplyText->value => $this->handleReplyText($conversation, $option, $language),
                ChatFlowOptionAction::TransferAgent->value => $this->handleTransferAgent($conversation, $option, $language, $previousStatus, $transitionToWaiting),
                ChatFlowOptionAction::CloseChat->value => $this->handleCloseChat($conversation, $option, $language),
                default => [
                    'success' => true,
                    'data' => [
                        'conversation_id' => $conversation->id,
                        'action' => $option->action,
                    ],
                ],
            };

            return $result;
        });

        if ($transitionToWaiting) {
            try {
                $this->telegram->broadbandApplicaiton(
                    $this->buildWaitingTelegramMessage($conversation, $option, $language),
                );
            } catch (\Throwable) {
                // Telegram failures must not fail the chat flow request.
            }
        }

        return response()->json($payload);
    }

    private function handleGoToStep(ChatConversation $conversation, ChatFlowOption $option, string $language): array
    {
        $nextStep = $option->nextStep()->first();

        if (! $nextStep) {
            abort(422, 'The selected option does not have a valid next step.');
        }

        $conversation->current_step_id = $nextStep->id;
        $conversation->save();

        $conversation->messages()->create([
            'sender_type' => ChatSenderType::System->value,
            'message' => $this->localizedValue($nextStep, 'message_' . $language),
            'option_id' => $option->id,
            'is_read' => true,
        ]);

        return [
            'success' => true,
            'data' => [
                'conversation_id' => $conversation->id,
                'action' => ChatFlowOptionAction::GoToStep->value,
                'step' => $this->buildStepPayload($nextStep, $language),
            ],
        ];
    }

    private function handleGoToUrl(ChatConversation $conversation, ChatFlowOption $option, string $language): array
    {
        $conversation->messages()->create([
            'sender_type' => ChatSenderType::System->value,
            'message' => $this->localizedValue($option, 'reply_text_' . $language) ?? 'Opening a link.',
            'option_id' => $option->id,
            'is_read' => true,
        ]);

        return [
            'success' => true,
            'data' => [
                'conversation_id' => $conversation->id,
                'action' => ChatFlowOptionAction::GoToUrl->value,
                'url' => $option->url,
                'message' => $this->localizedValue($option, 'reply_text_' . $language) ?? 'Opening a link.',
            ],
        ];
    }

    private function handleReplyText(ChatConversation $conversation, ChatFlowOption $option, string $language): array
    {
        $replyText = $this->localizedValue($option, 'reply_text_' . $language) ?? '';

        if ($replyText !== '') {
            $conversation->messages()->create([
                'sender_type' => ChatSenderType::System->value,
                'message' => $replyText,
                'option_id' => $option->id,
                'is_read' => true,
            ]);
        }

        return [
            'success' => true,
            'data' => [
                'conversation_id' => $conversation->id,
                'action' => ChatFlowOptionAction::ReplyText->value,
                'reply' => $replyText,
            ],
        ];
    }

    private function handleTransferAgent(
        ChatConversation $conversation,
        ChatFlowOption $option,
        string $language,
        ChatConversationStatus $previousStatus,
        bool &$transitionToWaiting,
    ): array {
        $status = ChatConversationStatus::WaitingAgent;
        $wasWaiting = $previousStatus === $status;

        $conversation->status = $status;
        $conversation->save();

        $message = $this->localizedValue($option, 'reply_text_' . $language)
            ?: 'You are now waiting for an agent.';

        $conversation->messages()->create([
            'sender_type' => ChatSenderType::System->value,
            'message' => $message,
            'option_id' => $option->id,
            'is_read' => true,
        ]);

        $transitionToWaiting = ! $wasWaiting && $previousStatus !== $status;

        return [
            'success' => true,
            'data' => [
                'conversation_id' => $conversation->id,
                'action' => ChatFlowOptionAction::TransferAgent->value,
                'status' => $status->value,
                'message' => $message,
            ],
        ];
    }

    private function handleCloseChat(ChatConversation $conversation, ChatFlowOption $option, string $language): array
    {
        $conversation->status = ChatConversationStatus::Closed;
        $conversation->save();

        $message = $this->localizedValue($option, 'reply_text_' . $language)
            ?: 'This chat has been closed.';

        $conversation->messages()->create([
            'sender_type' => ChatSenderType::System->value,
            'message' => $message,
            'option_id' => $option->id,
            'is_read' => true,
        ]);

        return [
            'success' => true,
            'data' => [
                'conversation_id' => $conversation->id,
                'action' => ChatFlowOptionAction::CloseChat->value,
                'status' => ChatConversationStatus::Closed->value,
                'message' => $message,
            ],
        ];
    }

    private function buildStepPayload(ChatFlowStep $step, string $language): array
    {
        $options = $step->options()->where('is_active', true)->orderBy('sort_order')->get();

        return [
            'id' => $step->id,
            'name' => $step->name,
            'message' => $this->localizedValue($step, 'message_' . $language),
            'options' => $options->map(function (ChatFlowOption $option) use ($language) {
                return [
                    'id' => $option->id,
                    'label' => $this->localizedValue($option, 'option_' . $language),
                    'action' => $option->action,
                ];
            })->values()->all(),
        ];
    }

    private function buildWaitingTelegramMessage(ChatConversation $conversation, ChatFlowOption $option, string $language): string
    {
        $customerName = $conversation->user?->name ?? 'Customer';
        $accountId = $conversation->user?->broadband_account_number ?? 'N/A';
        $optionLabel = $this->localizedValue($option, 'option_' . $language) ?? $option->option_en;

        return "New customer is waiting for support\n\n" .
            "Customer: {$customerName}\n" .
            "Account: {$accountId}\n" .
            "Conversation: #{$conversation->id}\n" .
            "Language: {$language}\n" .
            "Reason: {$optionLabel}";
    }

    private function getStartStep(): ChatFlowStep
    {
        $step = ChatFlowStep::query()->where('is_start', true)->first();

        if (! $step) {
            throw new HttpException(404, 'No chat flow start step has been configured.');
        }

        return $step;
    }

    private function resolveCustomerConversation($user, ?int $conversationId = null): ?ChatConversation
    {
        $query = $user->chatConversations();

        if ($conversationId) {
            $conversation = $query->find($conversationId);

            if ($conversation && $conversation->user_id !== $user->id) {
                throw new AccessDeniedHttpException();
            }

            return $conversation;
        }

        return $query
            ->whereNot('status', ChatConversationStatus::Closed->value)
            ->latest('updated_at')
            ->first();
    }

    private function resolveLanguage(mixed $language): string
    {
        $normalized = strtolower((string) ($language ?? 'en'));

        if (! in_array($normalized, ['en', 'my', 'zh'], true)) {
            abort(422, 'The language must be one of: en, my, zh.');
        }

        return $normalized;
    }

    private function localizedValue(object $model, string $field): ?string
    {
        return $model->{$field} ?? null;
    }
}
