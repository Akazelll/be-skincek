<?php

namespace App\Contracts;

use App\Models\User;

interface PushNotificationServiceContract
{
    /**
     * Kirim push notification ke seluruh device FCM yang terdaftar milik user.
     *
     * @param  array<string, string|int|bool|null>  $data
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): void;
}
