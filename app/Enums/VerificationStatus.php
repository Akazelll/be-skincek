<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case NEEDS_REVISION = 'needs_revision';
}
