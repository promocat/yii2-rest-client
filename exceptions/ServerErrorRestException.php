<?php


namespace promocat\rest\exceptions;


class ServerErrorRestException extends RestException
{
    public function canRetry()
    {
        return true;
    }
}