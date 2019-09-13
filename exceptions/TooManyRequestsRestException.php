<?php


namespace promocat\rest\exceptions;

class TooManyRequestsRestException extends RestException
{
    public function canRetry()
    {
        return true;
    }
}