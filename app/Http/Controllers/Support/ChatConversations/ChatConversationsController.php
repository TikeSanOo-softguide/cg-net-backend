<?php

namespace App\Http\Controllers\Support\ChatConversations;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\QuickReply;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChatConversationsController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $selectedId = $request->integer('conversation');

        $conversations = ChatConversation::query()
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
                        ->whereHas('user', fn($userQuery) => $userQuery->whereLike('name', "%{$search}%"))
                        ->orWhereHas('messages', fn($messageQuery) => $messageQuery->whereLike('message', "%{$search}%"));
                });
            })
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest('updated_at')
            ->get();

        $selectedConversation = null;

        if ($selectedId) {
            $selectedConversation = ChatConversation::query()
                ->with([
                    'user:id,name',
                    'agent:id,username',
                    'messages' => function ($query) {
                        $query->orderBy('created_at');
                    },
                    'currentStep.options.option_en',
                    'currentStep.options.option_my',
                    'currentStep.options.option_zh',
                ])
                ->find($selectedId);

            if ($selectedConversation) {
                $selectedConversation->messages()
                    ->where('sender_type', 'customer')
                    ->where('is_read', false)
                    ->update([
                        'is_read' => true,
                    ]);
            }
        } elseif ($conversations->isNotEmpty()) {
            $selectedConversation = ChatConversation::query()
                ->with([
                    'user:id,name',
                    'agent:id,username',
                    'messages' => function ($query) {
                        $query->orderBy('created_at');
                    },
                    'currentStep.options.option_en',
                    'currentStep.options.option_my',
                    'currentStep.options.option_zh',
                ])
                ->find($conversations->first()->id);
        }

        $quickReplies = QuickReply::query()
            ->orderBy('keyword')
            ->get();

        return Inertia::render(
            'Support/chatConversations/Index',
            [
                'conversations' => $conversations,
                'selectedConversation' => $selectedConversation,
                'quickReplies' => $quickReplies,
                'filters' => [
                    'search' => $search,
                    'status' => $status,
                ],
            ]
        );
    }

    public function sendMessage(
        Request $request,
        ChatConversation $conversation
    ) {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $conversation->messages()->create([
            'sender_type' => 'agent',
            'message_type' => 'text',
            'message' => $validated['message'],
            'is_read' => true,
        ]);

        $conversation->touch();

        return back();
    }

    public function updateStatus(
        Request $request,
        ChatConversation $conversation
    ) {
        $validated = $request->validate([
            'status' => ['required', 'in:open,waiting_agent,closed'],
        ]);

        $conversation->update([
            'status' => $validated['status'],
        ]);

        return back();
    }

    public function useQuickReply(
        Request $request,
        ChatConversation $conversation,
        QuickReply $quickReply
    ) {
        $language = $conversation->language ?: 'en';

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
