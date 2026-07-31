<?php

namespace App\Enums;

enum NotificationType: string
{
    case CHAT_MESSAGE_RECEIVED = 'chat_message_received';
    case DOCTOR_VERIFICATION_REVIEWED = 'doctor_verification_reviewed';
    case SCAN_RESULT_READY = 'scan_result_ready';
    case DAILY_CARE_RECOMMENDATION = 'daily_care_recommendation';

    public function category(): NotificationCategory
    {
        return match ($this) {
            self::CHAT_MESSAGE_RECEIVED,
            self::DOCTOR_VERIFICATION_REVIEWED,
            self::SCAN_RESULT_READY => NotificationCategory::TRANSACTIONAL,

            self::DAILY_CARE_RECOMMENDATION => NotificationCategory::REMINDER,
        };
    }
}
