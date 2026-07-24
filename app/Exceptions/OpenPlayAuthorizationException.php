<?php

namespace App\Exceptions;

use RuntimeException;

class OpenPlayAuthorizationException extends RuntimeException
{
    public function __construct(string $message = 'You are not allowed to do that.')
    {
        parent::__construct($message);
    }
}
