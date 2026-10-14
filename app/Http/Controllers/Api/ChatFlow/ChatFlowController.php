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
                if ($active->status->isChatFlow()) {
                    $this->lockFlowSteps();
                    $startStep = $this->getStartStep($language);
                    $active = ChatConversation::query()->lockForUpdate()->findOrFail($active->id);
                    $step = $active->currentStep;

                    if (! $step) {
                        $active->current_step_id = $startStep->id;
                        $active->save();
                        $step = $startStep;
                    }

                    if ($active->messages()->exists()) {
                        return [$active, null];
                    }

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

            $this->lockFlowSteps();
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

                if (! $conversation->status->isChatFlow()) {
                    return [$conversation, null];
                }

                $this->lockFlowSteps();
                $step = $conversation->currentStep;

                if (! $step) {
                    $step = $this->getStartStep($language);
                    $conversation->current_step_id = $step->id;
                    $conversation->save();
                }

                if ($conversation->messages()->exists()) {
                    return [$conversation, null];
                }

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
        $validated = $request->validate([
            'option_id' => ['required', 'integer'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $user = $this->customer($request);
        $conversation = $request->filled('conversation_id')
            ? $this->findCustomerConversation($user, (int) $request->input('conversation_id'))
            : $this->activeConversation($user);

        if (! $conversation) {
            abort(409, __('support.chat_conversations.no_active_conversation', [], $language));
        }

        $previousStatus = null;
        $recoveredPayload = null;

        [$payload, $createdMessages, $recoveredPayload, $selectedOption] = DB::transaction(function () use (&$conversation, &$previousStatus, &$recoveredPayload, $language, $validated) {
            $conversation = ChatConversation::query()->lockForUpdate()->findOrFail($conversation->id);

            if (! $conversation->status->isChatFlow()) {
                $this->rejectForState(
                    $conversation,
                    $conversation->status->isWaiting()
                        ? __('support.chat_conversations.conversation_waiting_for_agent', [], $language)
                        : __('support.chat_conversations.conversation_in_live_chat', [], $language),
                );
            }

            $this->lockFlowSteps();
            $option = ChatFlowOption::query()
                ->lockForUpdate()
                ->find((int) $validated['option_id']);

            if (! $option) {
                abort(404, __('support.chat_conversations.no_chat_flow_option', [], $language));
            }

            if (! $option->is_active) {
                abort(422, __('support.chat_conversations.this_option_is_no_longer_active', [], $language));
            }

            $currentStep = $conversation->currentStep;

            if (! $currentStep) {
                $startStep = $this->getStartStep($language);
                $conversation->current_step_id = $startStep->id;
                $conversation->save();
                $recoveredPayload = $this->conversationPayload($conversation, $language);

                return [null, collect(), $recoveredPayload, null];
            }

            if ($option->step_id !== $conversation->current_step_id) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'code' => 'stale_chat_option',
                    'message' => __('support.chat_conversations.option_no_longer_available', [], $language),
                    'data' => $this->conversationPayload($conversation, $language),
                ], 409));
            }

            if ($option->action === ChatFlowOptionAction::GoToStep->value) {
                $destination = $option->nextStep()->first();

                if (! $destination) {
                    throw new HttpResponseException(response()->json([
                        'success' => false,
                        'code' => 'stale_chat_option',
                        'message' => __('support.chat_conversations.option_no_longer_available', [], $language),
                        'data' => $this->conversationPayload($conversation, $language),
                    ], 409));
                }
            }

            $previousStatus = $conversation->status;
            $selectedLabel = $this->localizedValue($option, 'option_' . $language);

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

            return [$result, $createdMessages, null, $option];
        });

        if ($recoveredPayload) {
            return response()->json([
                'success' => false,
                'code' => 'stale_chat_option',
                'message' => __('support.chat_conversations.option_no_longer_available', [], $language),
                'data' => $recoveredPayload,
            ], 409);
        }

        foreach ($createdMessages as $message) {
            rescue(fn() => ChatMessageCreated::dispatch($message));
        }

        if ($previousStatus !== $conversation->status) {
            $this->broadcastStatusChanged($conversation, $previousStatus);

            if ($conversation->status->isWaiting() && $selectedOption) {
                $this->notifyAgentsOfWaitingConversation($conversation, $selectedOption, $language);
            }
        }

        return response()->json($payload);
    }

    public function navigate(Request $request): JsonResponse
    {
        $language = $this->resolveLanguage($request->input('language'));
        $validated = $request->validate([
            'conversation_id' => ['required', 'integer'],
            'action' => ['required', 'in:main_menu'],
        ]);
        $user = $this->customer($request);
        $conversation = $this->findCustomerConversation(
            $user,
            (int) $validated['conversation_id'],
        );

        $payload = DB::transaction(function () use (&$conversation, $language) {
            $conversation = ChatConversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            if (! $conversation->status->isChatFlow()) {
                $message = match (true) {
                    $conversation->status->isWaiting() => __('support.chat_conversations.conversation_waiting_for_agent', [], $language),
                    $conversation->status->isLive() => __('support.chat_conversations.conversation_in_live_chat', [], $language),
                    $conversation->status === ChatConversationStatus::Closed => __('support.chat_conversations.conversation_closed', [], $language),
                    default => __('support.chat_conversations.select_chat_flow_option', [], $language),
                };
                $this->rejectForState(
                    $conversation,
                    $message,
                );
            }

            $this->lockFlowSteps();
            $startStep = $this->getStartStep($language);
            $conversation->current_step_id = $startStep->id;
            $conversation->save();

            return $this->conversationPayload($conversation, $language);
        });

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
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
        $options = $step->options()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

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
        $startStep = $conversation->status->isChatFlow()
            ? $this->getStartStep($language)
            : null;
        $step = $conversation->status->isChatFlow()
            ? ($conversation->currentStep ?? $startStep)
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
        $steps = ChatFlowStep::query()
            ->where('is_start', true)
            ->orderBy('id')
            ->lockForUpdate()
            ->limit(2)
            ->get();

        if ($steps->count() !== 1) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'code' => 'invalid_start_step_configuration',
                'message' => __('support.chat_conversations.invalid_start_step_configuration', [], $language),
            ], 409));
        }

        return $steps->sole();
    }

    private function lockFlowSteps(): void
    {
        ChatFlowStep::query()
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
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
