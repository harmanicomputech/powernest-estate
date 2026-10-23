<?php

namespace App\Integrations\Payments;

enum VerificationStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Pending = 'pending';
    case NotFound = 'not_found';
}
