<?php


namespace promocat\rest\exceptions;

class ServiceUnavailableRestException extends RestException
{
    public function canRetry()
    {
        return true;
    }
}