<?php


namespace promocat\rest\components;


use promocat\rest\exceptions\BadRequestRestException;
use promocat\rest\exceptions\DataValidationRestException;
use promocat\rest\exceptions\ForbiddenRestException;
use promocat\rest\exceptions\NotAcceptableRestException;
use promocat\rest\exceptions\NotAllowedRestException;
use promocat\rest\exceptions\ServerErrorRestException;
use promocat\rest\exceptions\ServiceUnavailableRestException;
use promocat\rest\exceptions\TooManyRequestsRestException;
use promocat\rest\exceptions\UnauthorizedRestException;
use yii\httpclient\Response;

class RestResponse extends Response
{
    public function getIsOk()
    {
        if (parent::getIsOk()) {
            return true;
        }
        $code = $this->getStatusCode();
        $retryAfter = null;
        switch ($code) {
            case '503':
                throw new ServiceUnavailableRestException($this, 'Service unavailable', $code);
                break;
            case '500':
                throw new ServerErrorRestException($this, 'Server error', $code);
                break;
            case '429':
                throw new TooManyRequestsRestException($this, 'Too many requests', $code);
                break;
            case '422':
                throw new DataValidationRestException($this, 'Data validation failed', $code);
                break;
            case '406':
                throw new NotAcceptableRestException($this, 'Not acceptable', $code);
                break;
            case '405':
                throw new NotAllowedRestException($this, 'Not allowed', $code);
                break;
            case '403':
                throw new ForbiddenRestException($this, 'Forbidden', $code);
                break;
            case '401':
                throw new UnauthorizedRestException($this, 'Unauthorized', $code);
                break;
            case '400':
                throw new BadRequestRestException($this, 'Unauthorized', $code);
                break;
        }
        return false;
    }
}