<?php

namespace App\Http\Controllers\Api\ChatFlow;

use App\Enums\ChatConversationStatus;
use App\Enums\ChatFlowOptionAction;
use App\Enums\ChatSenderType;
use App\Events\ChatConversationStatusChanged;
use App\Events\ChatMessageCreated;
use App\Http\Controllers\Controller;
use App\Http\Resources\Chat\ChatMessageResource;
use App\Jobs\SendChatWaitingTelegramNotification;
use App\Models\ChatConversation;
use App\Models\ChatFlowOption;
use App\Models\ChatFlowStep;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ChatFlowController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        $language = $this->resolveLanguage($request->input('language'));
        $user = $this->customer($request);

        [$conversation, $createdMessage] = DB::transaction(function () use ($user, $language) {
            // Serialise start requests per customer so concurrent calls cannot create two active conversations.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $active = $this->activeConversation($user);

            if ($active) {
                if ($active->status->isChatFlow() && ! $active->messages()->exists()) {
                    $step = $active->currentStep ?? $this->getStartStep($language);
                    $message = $active->messages()->create([
                        'sender_type' => ChatSenderType::System->value,
                        'message' => $this->localizedValue($step, 'message_' . $language),
                        'language' => $language,
                        'step_id' => $step->id,
                        'is_read' => true,
                    ]);
                    return [$active, $message];
                }

                return [$active, null];
            }

            $startStep = $this->getStartStep($language);
            $conversation = $user->chatConversations()->create([
                'current_step_id' => $startStep->id,
                'status' => ChatConversationStatus::Bot,
            ]);

            $message = $conversation->messages()->create([
                'sender_type' => ChatSenderType::System->value,
                'message' => $this->localizedValue($startStep, 'message_' . $language),
                'language' => $language,
                'step_id' => $startStep->id,
                'is_read' => true,
            ]);

            return [$conversation, $message];
        });

        if ($createdMessage) {
            rescue(fn() => ChatMessageCreated::dispatch($createdMessage));
        }

        return response()->json([
            'success' => true,
            'data' => $this->conversationPayload($conversation, $language),
        ]);
    }

    public function current(Request $request): JsonResponse
    {
        $language = $this->resolveLanguage($request->input('language'));
        $request->validate(['conversation_id' => ['nullable', 'integer']]);

        $user = $this->customer($request);
        $conversation = $request->filled('conversation_id')
            ? $this->findCustomerConversation($user, (int) $request->input('conversation_id'))
            : ($this->activeConversation($user) ?? $user->chatConversations()->latest('id')->first());

        $createdMessage = null;
        if ($conversation?->status->isChatFlow()) {
            [$conversation, $createdMessage] = DB::transaction(function () use ($conversation, $language) {
                $conversation = ChatConversation::query()
                    ->lockForUpdate()
                    ->findOrFail($conversation->id);

                if (! $conversation->status->isChatFlow() || $conversation->messages()->exists()) {
                    return [$conversation, null];
                }

                $step = $conversation->currentStep ?? $this->getStartStep($language);
                $message = $conversation->messages()->create([
                    'sender_type' => ChatSenderType::System->value,
                    'message' => $this->localizedValue($step, 'message_' . $language),
                    'language' => $language,
                    'step_id' => $step->id,
                    'is_read' => true,
                ]);

                return [$conversation, $message];
            });
        }

        if ($createdMessage) {
            rescue(fn() => ChatMessageCreated::dispatch($createdMessage));
        }

        return response()->json([
            'success' => true,
            'data' => $conversation
                ? $this->conversationPayload($conversation, $language)
                : $this->emptyConversationPayload(),
        ]);
    }

    public function selectOption(Request $request): JsonResponse
    {
        $language = $this->resolveLanguage($request->input('language'));
        $request->validate([
            'option_id' => ['required', 'integer'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $conversation = $request->filled('conversation_id')
            ? $this->findCustomerConversation($this->customer($request), (int) $request->input('conversation_id'))
            : $this->activeConversation($this->customer($request));

        if (! $conversation) {
            abort(409, __('support.chat_conversations.no_active_conversation', [], $language));
        }

        $option = ChatFlowOption::query()->with(['step', 'nextStep'])->find((int) $request->input('option_id'));

        if (! $option) {
            abort(404, __('support.chat_conversations.no_chat_flow_option', [], $language));
        }

        $selectedLabel = $this->localizedValue($option, 'option_' . $language);
        $previousStatus = null;

        [$payload, $createdMessages] = DB::transaction(function () use (&$conversation, &$previousStatus, $language, $option, $selectedLabel) {
            $conversation = ChatConversation::query()->lockForUpdate()->findOrFail($conversation->id);

            if (! $conversation->status->isChatFlow()) {
                $this->rejectForState(
                    $conversation,
                    $conversation->status->isWaiting()
                        ? __('support.chat_conversations.conversation_waiting_for_agent', [], $language)
                        : __('support.chat_conversations.conversation_in_live_chat', [], $language),
                );
            }

            if (! $option->is_active) {
                abort(422, __('support.chat_conversations.this_option_is_no_longer_active', [], $language));
            }

            if ($option->step_id !== $conversation->current_step_id) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'code' => 'stale_chat_option',
                    'message' => __('support.chat_conversations.option_no_longer_available', [], $language),
                    'data' => $this->conversationPayload($conversation, $language),
                ], 409));
            }

            $previousStatus = $conversation->status;

            $existingMessageIds = $conversation->messages()->pluck('id');
            if ($option->action !== ChatFlowOptionAction::GoToUrl->value) {
                $conversation->messages()->create([
                    'sender_type' => ChatSenderType::Customer->value,
                    'message' => $selectedLabel,
                    'language' => $language,
                    'option_id' => $option->id,
                    'step_id' => $conversation->current_step_id,
                    'is_read' => true,
                ]);
            }

            $result = match ($option->action) {
                ChatFlowOptionAction::GoToStep->value => $this->handleGoToStep($conversation, $option, $language),
                ChatFlowOptionAction::GoToUrl->value => $this->handleGoToUrl($conversation, $option),
                ChatFlowOptionAction::ReplyText->value => $this->handleReplyText($conversation, $option, $language),
                ChatFlowOptionAction::TransferAgent->value => $this->handleTransferAgent($conversation, $option, $language),
                ChatFlowOptionAction::CloseChat->value => $this->handleCloseChat($conversation, $option, $language),
                default => [
                    'success' => true,
                    'data' => [
                        'conversation_id' => $conversation->id,
                        'action' => $option->action,
                    ],
                ],
            };

            $conversation->touch();

            $result['data'] = array_merge(
                $this->conversationPayload($conversation, $language),
                $result['data'],
            );

            $createdMessages = $conversation->messages()
                ->whereNotIn('id', $existingMessageIds)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            return [$result, $createdMessages];
        });

        foreach ($createdMessages as $message) {
            rescue(fn() => ChatMessageCreated::dispatch($message));
        }

        if ($previousStatus !== $conversation->status) {
            $this->broadcastStatusChanged($conversation, $previousStatus);

            if ($conversation->status->isWaiting()) {
                $this->notifyAgentsOfWaitingConversation($conversation, $option, $language);
            }
        }

        return response()->json($payload);
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $language = $this->resolveLanguage($request->input('language'));
        $validated = $request->validate([
            'conversation_id' => ['required', 'integer'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $conversation = $this->findCustomerConversation($this->customer($request), (int) $validated['conversation_id']);

        $message = DB::transaction(function () use ($conversation, $validated, $language) {
            $conversation = ChatConversation::query()->lockForUpdate()->findOrFail($conversation->id);

            if (! $conversation->status->isLive()) {
                $this->rejectForState($conversation, match (true) {
                    $conversation->status === ChatConversationStatus::Closed => __('support.chat_conversations.conversation_closed', [], $language),
                    $conversation->status->isWaiting() => __('support.chat_conversations.waiting_for_agent', [], $language),
                    default => __('support.chat_conversations.select_chat_flow_option', [], $language),
                });
            }

            $message = $conversation->messages()->create([
                'sender_type' => ChatSenderType::Customer->value,
                'message' => $validated['message'],
                'is_read' => false,
            ]);

            $conversation->touch();

            return $message;
        });

        rescue(fn() => ChatMessageCreated::dispatch($message));

        return response()->json([
            'success' => true,
            'data' => [
                'conversation_id' => $message->conversation_id,
                'message' => (new ChatMessageResource($message))->resolve(),
            ],
        ], 201);
    }

    private function handleGoToStep(ChatConversation $conversation, ChatFlowOption $option, string $language): array
    {
        $nextStep = $option->nextStep()->first();

        if (! $nextStep) {
            abort(422, __('support.chat_conversations.no_valid_next_step', [], $language));
        }

        $conversation->current_step_id = $nextStep->id;
        $conversation->save();

        $conversation->messages()->create([
            'sender_type' => ChatSenderType::System->value,
            'message' => $this->localizedValue($nextStep, 'message_' . $language),
            'language' => $language,
            'option_id' => $option->id,
            'step_id' => $nextStep->id,
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

    private function handleGoToUrl(ChatConversation $conversation, ChatFlowOption $option): array
    {
        return [
            'success' => true,
            'data' => [
                'conversation_id' => $conversation->id,
                'action' => ChatFlowOptionAction::GoToUrl->value,
                'url' => $option->url,
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
                'language' => $language,
                'option_id' => $option->id,
                'step_id' => $option->step_id,
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

    private function handleTransferAgent(ChatConversation $conversation, ChatFlowOption $option, string $language): array
    {
        $status = ChatConversationStatus::WaitingAgent;

        $conversation->status = $status;
        $conversation->agent_id = null;
        $conversation->save();

        $message = $this->localizedValue($option, 'reply_text_' . $language)
            ?: __('support.chat_conversations.reply_text_waiting_stage', [], $language);

        $conversation->messages()->create([
            'sender_type' => ChatSenderType::System->value,
            'message' => $message,
            'language' => $language,
            'is_read' => true,
        ]);

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
            ?: __('support.chat_conversations.reply_text_closed', [], $language);

        $conversation->messages()->create([
            'sender_type' => ChatSenderType::System->value,
            'message' => $message,
            'language' => $language,
            'option_id' => $option->id,
            'step_id' => $option->step_id,
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

    private function conversationPayload(ChatConversation $conversation, string $language): array
    {
        $step = $conversation->status->isChatFlow()
            ? ($conversation->currentStep ?? $this->getStartStep($language))
            : null;

        $messages = ChatMessage::query()
            ->with(['option.step.options'])
            ->whereIn(
                'conversation_id',
                ChatConversation::query()
                    ->select('id')
                    ->where('user_id', $conversation->user_id),
            )
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return array_merge($this->statePayload($conversation), [
            'step' => $step ? $this->buildStepPayload($step, $language) : null,
            'messages' => ChatMessageResource::collection($messages)->resolve(),
        ]);
    }

    private function statePayload(ChatConversation $conversation): array
    {
        $status = $conversation->status;
        $agent = $conversation->agent;

        return [
            'conversation_id' => $conversation->id,
            'status' => $status->value,
            'agent' => $agent ? ['id' => $agent->id, 'username' => $agent->username] : null,
            'can_send_message' => $status->isLive(),
            'can_select_option' => $status->isChatFlow(),
            'is_closed' => $status === ChatConversationStatus::Closed,
            'can_start_new_conversation' => $status === ChatConversationStatus::Closed,
        ];
    }

    private function emptyConversationPayload(): array
    {
        return [
            'conversation_id' => null,
            'status' => null,
            'agent' => null,
            'can_send_message' => false,
            'can_select_option' => false,
            'is_closed' => false,
            'can_start_new_conversation' => true,
            'step' => null,
            'messages' => [],
        ];
    }

    private function rejectForState(ChatConversation $conversation, string $message): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'data' => $this->statePayload($conversation),
        ], 409));
    }

    private function broadcastStatusChanged(ChatConversation $conversation, ?ChatConversationStatus $previousStatus): void
    {
        rescue(fn() => ChatConversationStatusChanged::dispatch($conversation, $previousStatus));
    }

    private function notifyAgentsOfWaitingConversation(ChatConversation $conversation, ChatFlowOption $option, string $language): void
    {
        try {
            SendChatWaitingTelegramNotification::dispatch(
                $conversation->id,
                $this->buildWaitingTelegramMessage($conversation, $option, $language),
            );
        } catch (\Throwable $exception) {
            // Telegram failures must not undo or fail the transition to waiting.
            report($exception);
        }
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

    private function getStartStep(string $language): ChatFlowStep
    {
        $step = ChatFlowStep::query()->where('is_start', true)->first();

        if (! $step) {
            throw new HttpException(404, __('support.chat_conversations.no_start_step_configured', [], $language));
        }

        return $step;
    }

    private function customer(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AccessDeniedHttpException('Only customers can use the chat API.');
        }

        return $user;
    }

    private function activeConversation(User $user): ?ChatConversation
    {
        return $user->chatConversations()->active()->latest('id')->first();
    }

    private function findCustomerConversation(User $user, int $conversationId): ChatConversation
    {
        $conversation = ChatConversation::query()->find($conversationId);

        if (! $conversation) {
            abort(404, 'Conversation not found.');
        }

        if ((int) $conversation->user_id !== (int) $user->id) {
            throw new AccessDeniedHttpException('You do not have access to this conversation.');
        }

        return $conversation;
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
