<?php

namespace App\Exceptions;

use RuntimeException;

class NonContiguousSlotsException extends RuntimeException
{
    public function __construct(string $message = 'Selected slots must be back to back with no gaps.')
    {
        parent::__construct($message);
    }
}
