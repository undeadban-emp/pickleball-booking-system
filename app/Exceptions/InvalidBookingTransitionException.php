<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidBookingTransitionException extends RuntimeException
{
    public function __construct(string $from, string $action)
    {
        parent::__construct("Booking cannot be {$action} while in status \"{$from}\".");
    }
}
