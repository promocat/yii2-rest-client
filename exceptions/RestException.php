<?php

namespace promocat\rest\exceptions;

use promocat\rest\components\RestResponse;

abstract class RestException extends \Exception
{
    public $response = null;

    public function __construct(RestResponse $response, string $message = "", int $code = 0, Throwable $previous = null)
    {
        $this->response = $response;
        parent::__construct($message, $code, $previous);
    }

    public function canRetry()
    {
        return false;
    }

    public function getRetryAfter(bool $keepDate, bool $inMilliseconds)
    {
        $headers = $this->response->headers;
        $retryAfter = $headers->get('retry-after');
        if (empty($retryAfter)) {
            return null;
        }
        if(!is_numeric($retryAfter) && !$keepDate) {
            $currentTimeStamp = strtotime("now");
            $retryTimestamp = strtotime($retryAfter);
            $retryAfter = $retryTimestamp - $currentTimeStamp;
        }
        if($inMilliseconds) {
            return $retryAfter * 1000;
        }
        return $retryAfter;
    }

    public function getContentNegotiationHeaders()
    {
        $headers = $this->response->headers;
        $contentNegotiationHeaders = null;
        if (!empty($headers->get('Accept'))) {
            $contentNegotiationHeaders['Accept'] = $headers->get('Accept');
        }
        if (!empty($headers->get('Accept-Charset'))) {
            $contentNegotiationHeaders['Accept-Charset'] = $headers->get('Accept-Charset');
        }
        if (!empty($headers->get('Accept-Encoding'))) {
            $contentNegotiationHeaders['Accept-Encoding'] = $headers->get('Accept-Encoding');
        }
        if (!empty($headers->get('Accept-Language'))) {
            $contentNegotiationHeaders['Accept-Language'] = $headers->get('Accept-Language');
        }
        return $contentNegotiationHeaders;
    }

    public function getAllowHeaders()
    {
        $headers = $this->response->headers;
        $allowHeaders = null;
        if (!empty($headers->get('Allow'))) {
            $allowHeaders['Allow'] = $headers['Allow'];
        }
        return $allowHeaders;
    }
}