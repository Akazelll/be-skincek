<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{uuid}', function ($user, $uuid) {
    Log::info('Channel auth attempt', [
        'user_uuid' => $user->uuid,
        'param_uuid' => $uuid,
        'match' => $user->uuid === $uuid,
        'user_id' => $user->id,
        'user_type' => get_class($user),
    ]);

    return $user->uuid === $uuid;
});

Broadcast::channel('conversation.{uuid}', function (User $user, string $uuid) {
    return Conversation::where('uuid', $uuid)
        ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('doctor_id', $user->id))
        ->exists();
});
