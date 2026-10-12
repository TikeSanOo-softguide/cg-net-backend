<?php

namespace App\Http\Controllers\Support\ChatConversations;

use App\Enums\ChatConversationStatus;
use App\Enums\ChatSenderType;
use App\Enums\QuickReplyCategory;
use App\Events\ChatConversationStatusChanged;
use App\Events\ChatMessageCreated;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\QuickReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ChatConversationsController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $selectedId = $request->integer('conversation');

        $selectedConversation = null;

        if ($selectedId) {
            $selectedConversation = ChatConversation::query()->find($selectedId);

            if ($selectedConversation?->user_id !== null) {
                $selectedConversation = ChatConversation::query()
                    ->where('user_id', $selectedConversation->user_id)
                    ->latest('id')
                    ->first();
            }

            if ($selectedConversation) {
                $selectedConversation->load(['user:id,name', 'agent:id,username', 'currentStep.options']);
                $this->markHistoryAsRead($selectedConversation);
                $this->loadConversationHistory($selectedConversation);
            }
        }

        $conversations = ChatConversation::query()
            ->where(function ($query) {
                $query
                    ->whereNull('chat_conversations.user_id')
                    ->orWhereNotExists(function ($query) {
                        $query
                            ->selectRaw('1')
                            ->from('chat_conversations as newer_conversations')
                            ->whereColumn('newer_conversations.user_id', 'chat_conversations.user_id')
                            ->whereColumn('newer_conversations.id', '>', 'chat_conversations.id')
                            ->whereNull('newer_conversations.deleted_at');
                    });
            })
            ->with([
                'user:id,name',
                'agent:id,username',
                'latestMessage' => function ($query) {
                    $query->select([
                        'chat_messages.id',
                        'chat_messages.conversation_id',
                        'chat_messages.sender_type',
                        'chat_messages.message',
                        'chat_messages.created_at',
                        'chat_messages.is_read',
                    ]);
                },
            ])
            ->withCount([
                'messages as unread_count' => function ($query) {
                    $query
                        ->where('sender_type', 'customer')
                        ->where('is_read', false);
                },
            ])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->whereHas('user', fn ($userQuery) => $userQuery->whereLike('name', "%{$search}%"))
                        ->orWhereHas('messages', fn ($messageQuery) => $messageQuery->whereLike('message', "%{$search}%"))
                        ->orWhereExists(function ($historyQuery) use ($search) {
                            $historyQuery
                                ->selectRaw('1')
                                ->from('chat_conversations as history_conversations')
                                ->join('chat_messages as history_messages', 'history_messages.conversation_id', '=', 'history_conversations.id')
                                ->whereColumn('history_conversations.user_id', 'chat_conversations.user_id')
                                ->whereLike('history_messages.message', "%{$search}%")
                                ->whereNull('history_conversations.deleted_at');
                        });
                });
            })
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest('updated_at')
            ->get();

        if (! $selectedConversation && $conversations->isNotEmpty()) {
            $selectedConversation = ChatConversation::query()
                ->with(['user:id,name', 'agent:id,username', 'currentStep.options'])
                ->find($conversations->first()->id);

            if ($selectedConversation) {
                $this->markHistoryAsRead($selectedConversation);
                $this->loadConversationHistory($selectedConversation);
            }
        }

        $userIds = $conversations->pluck('user_id')->filter()->unique();

        if ($userIds->isNotEmpty()) {
            $unreadCounts = DB::table('chat_messages')
                ->join('chat_conversations as history_conversations', 'history_conversations.id', '=', 'chat_messages.conversation_id')
                ->whereIn('history_conversations.user_id', $userIds)
                ->whereNull('history_conversations.deleted_at')
                ->where('chat_messages.sender_type', ChatSenderType::Customer->value)
                ->where('chat_messages.is_read', false)
                ->groupBy('history_conversations.user_id')
                ->selectRaw('history_conversations.user_id, COUNT(*) as unread_count')
                ->pluck('unread_count', 'history_conversations.user_id');

            $conversations->each(function (ChatConversation $conversation) use ($unreadCounts, $selectedConversation) {
                if ($conversation->user_id !== null) {
                    $count = (int) ($unreadCounts[$conversation->user_id] ?? 0);

                    if (
                        ($conversation->user_id !== null && $selectedConversation?->user_id === $conversation->user_id) ||
                        ($conversation->user_id === null && $selectedConversation?->id === $conversation->id)
                    ) {
                        $count = 0;
                    }

                    $conversation->setAttribute('unread_count', $count);
                } elseif ($selectedConversation?->id === $conversation->id) {
                    $conversation->setAttribute('unread_count', 0);
                }
            });
        }

        $quickReplies = QuickReply::query()
            ->orderBy('keyword')
            ->get(['id', 'keyword', 'category', 'response_en', 'response_my', 'response_zh']);

        return Inertia::render(
            'Support/chatConversations/Index',
            [
                'conversations' => $conversations,
                'selectedConversation' => $selectedConversation,
                'quickReplies' => $quickReplies,
                'quickReplyCategories' => array_map(
                    fn (QuickReplyCategory $category) => $category->value,
                    QuickReplyCategory::cases(),
                ),
                'filters' => [
                    'search' => $search,
                    'status' => $status,
                ],
            ]
        );
    }

    private function loadConversationHistory(ChatConversation $conversation): void
    {
        $conversationIds = ChatConversation::query()
            ->when(
                $conversation->user_id !== null,
                fn ($query) => $query->where('user_id', $conversation->user_id),
                fn ($query) => $query->whereKey($conversation->id),
            )
            ->select('id');

        $messages = ChatMessage::query()
            ->whereIn('conversation_id', $conversationIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $conversation->setRelation('messages', $messages);
    }

    private function markHistoryAsRead(ChatConversation $conversation): void
    {
        $conversationIds = ChatConversation::query()
            ->when(
                $conversation->user_id !== null,
                fn ($query) => $query->where('user_id', $conversation->user_id),
                fn ($query) => $query->whereKey($conversation->id),
            )
            ->select('id');

        DB::table('chat_messages')
            ->whereIn('conversation_id', $conversationIds)
            ->where('sender_type', ChatSenderType::Customer->value)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    public function sendMessage(
        Request $request,
        ChatConversation $conversation
    ) {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $message = DB::transaction(function () use ($conversation, $validated) {
            $conversation = ChatConversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            if ($conversation->status === ChatConversationStatus::Closed) {
                throw ValidationException::withMessages([
                    'conversation' => 'This conversation is closed and cannot receive new messages.',
                ]);
            }

            if (! $conversation->status->isLive()) {
                throw ValidationException::withMessages([
                    'conversation' => 'Accept this conversation before sending a message.',
                ]);
            }

            $message = $conversation->messages()->create([
                'sender_type' => ChatSenderType::Agent,
                'message' => $validated['message'],
                'is_read' => true,
            ]);

            $conversation->touch();

            return $message;
        });

        rescue(fn () => ChatMessageCreated::dispatch($message));

        return back();
    }

    public function accept(Request $request, ChatConversation $conversation)
    {
        $this->assignToAgent($conversation, $request->user());

        return back();
    }

    public function updateStatus(
        Request $request,
        ChatConversation $conversation
    ) {
        $validated = $request->validate([
            'status' => ['required', 'in:open,waiting_agent'],
        ]);

        if ($validated['status'] === ChatConversationStatus::Open->value) {
            $this->assignToAgent($conversation, $request->user());

            return back();
        }

        [$conversation, $previousStatus] = DB::transaction(function () use ($conversation) {
            $conversation = ChatConversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            $this->ensureNotClosed($conversation);

            if (! $conversation->status->isLive()) {
                throw ValidationException::withMessages([
                    'conversation' => 'Only live conversations can be returned to the waiting queue.',
                ]);
            }

            $previousStatus = $conversation->status;

            $conversation->update([
                'status' => ChatConversationStatus::WaitingAgent,
                'agent_id' => null,
            ]);

            return [$conversation, $previousStatus];
        });

        rescue(fn () => ChatConversationStatusChanged::dispatch($conversation, $previousStatus));

        return back();
    }

    public function close(Request $request, ChatConversation $conversation)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        [$conversation, $message, $previousStatus] = DB::transaction(function () use ($conversation, $validated) {
            $conversation = ChatConversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            if ($conversation->status === ChatConversationStatus::Closed) {
                throw ValidationException::withMessages([
                    'conversation' => 'This conversation is already closed.',
                ]);
            }

            $previousStatus = $conversation->status;

            $message = $conversation->messages()->create([
                'sender_type' => ChatSenderType::Agent,
                'message' => $validated['message'],
                'is_read' => true,
            ]);

            $conversation->update([
                'status' => ChatConversationStatus::Closed,
            ]);

            return [$conversation, $message, $previousStatus];
        });

        rescue(fn () => ChatMessageCreated::dispatch($message));
        rescue(fn () => ChatConversationStatusChanged::dispatch($conversation, $previousStatus));

        return back();
    }

    private function assignToAgent(ChatConversation $conversation, Admin $admin): void
    {
        $result = DB::transaction(function () use ($conversation, $admin) {
            $conversation = ChatConversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            $this->ensureNotClosed($conversation);

            if ($conversation->status->isLive()) {
                if ((int) $conversation->agent_id === (int) $admin->id) {
                    return null;
                }

                throw ValidationException::withMessages([
                    'conversation' => 'This conversation has already been accepted by another agent.',
                ]);
            }

            if (! $conversation->status->isWaiting()) {
                throw ValidationException::withMessages([
                    'conversation' => 'The customer has not requested an agent yet.',
                ]);
            }

            $previousStatus = $conversation->status;

            $conversation->update([
                'status' => ChatConversationStatus::Open,
                'agent_id' => $admin->id,
            ]);

            $message = $conversation->messages()->create([
                'sender_type' => ChatSenderType::System,
                'message' => "{$admin->username} has joined the conversation.",
                'is_read' => true,
            ]);

            return [$conversation, $message, $previousStatus];
        });

        if ($result === null) {
            return;
        }

        [$conversation, $message, $previousStatus] = $result;

        rescue(fn () => ChatMessageCreated::dispatch($message));
        rescue(fn () => ChatConversationStatusChanged::dispatch($conversation, $previousStatus));
    }

    private function ensureNotClosed(ChatConversation $conversation): void
    {
        if ($conversation->status === ChatConversationStatus::Closed) {
            throw ValidationException::withMessages([
                'conversation' => 'Closed conversations cannot be reopened. The customer must start a new conversation.',
            ]);
        }
    }

    public function useQuickReply(
        Request $request,
        ChatConversation $conversation,
        QuickReply $quickReply
    ) {
        $language = $request->string('language')->toString();

        if (! in_array($language, ['en', 'my', 'zh'], true)) {
            $language = 'en';
        }

        $field = match ($language) {
            'my' => 'response_my',
            'zh' => 'response_zh',
            default => 'response_en',
        };

        return response()->json([
            'message' => $quickReply->{$field},
        ]);
    }
}
