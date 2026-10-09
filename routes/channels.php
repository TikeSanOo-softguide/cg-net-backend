<?php

use App\Models\Admin;
use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return $user instanceof User && (int) $user->id === (int) $id;
});

Broadcast::channel('admin.notifications', function ($admin) {
    return $admin instanceof Admin && $admin->can('notifications.view');
});

Broadcast::channel('chat.conversation.{conversation}', function ($user, ChatConversation $conversation) {
    if ($user instanceof User) {
        return (int) $conversation->user_id === (int) $user->id;
    }

    return $user instanceof Admin && $user->can('support.view');
});

Broadcast::channel('support.conversations', function ($admin) {
    return $admin instanceof Admin && $admin->can('support.view');
});
