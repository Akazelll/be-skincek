<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case TRANSACTIONAL = 'transactional';
    case REMINDER = 'reminder';
}
