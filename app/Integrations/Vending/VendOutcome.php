<?php

namespace App\Integrations\Vending;

enum VendOutcome: string
{
    /** Token returned. */
    case Success = 'success';

    /** The vendor answered and did not issue a token. */
    case Failed = 'failed';

    /** The request provably never reached the vendor (DNS / connection refused). Safe to retry. */
    case NotSent = 'not_sent';

    /** Timeout, 5xx or unreadable response: the vendor may or may not have issued a token. Never auto-retry. */
    case Unknown = 'unknown';
}
