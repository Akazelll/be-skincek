<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{uuid}', function ($user, $uuid) {
    return $user->uuid === $uuid;
});

Broadcast::channel('conversation.{uuid}', function (User $user, string $uuid) {
    return Conversation::where('uuid', $uuid)
        ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('doctor_id', $user->id))
        ->exists();
});
