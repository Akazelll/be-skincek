<?php

namespace App\Enums;

enum ScanMode: string
{
    case UPLOAD = 'upload';
    case LIVECAM = 'livecam';
}
