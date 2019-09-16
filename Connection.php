<?php

namespace promocat\rest;

use Exception;
use promocat\rest\exceptions\BadRequestRestException;
use promocat\rest\exceptions\ForbiddenRestException;
use promocat\rest\exceptions\NotAcceptableRestException;
use promocat\rest\exceptions\NotAllowedRestException;
use promocat\rest\exceptions\RestException;
use promocat\rest\exceptions\ServerErrorRestException;
use promocat\rest\exceptions\ServiceUnavailableRestException;
use promocat\rest\exceptions\TooManyRequestsRestException;
use promocat\rest\exceptions\UnauthorizedRestException;
use promocat\rest\components\RestResponse;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\httpclient\Client;
use yii\log\Logger;
use yii\web\HeaderCollection;

/**
 * Class Connection
 *
 * Example configuration:
 * ```php
 * 'components' => [
 *     'restclient' => [
 *         'class' => 'promocat\yii2\rest\Connection',
 *         'config' => [
 *             'base_uri' => 'https://api.site.com/',
 *         ],
 *     ],
 * ],
 * ```
 *
 * @property Client $handler
 * @property null|string|\Closure $auth
 */
class Connection extends Component
{
    /**
     * @event Event an event that is triggered after a DB connection is established
     */
    const EVENT_AFTER_OPEN = 'afterOpen';
    /**
     * @var Client
     */
    protected static $_handler = null;

    /**
     * @var string base request URL.
     */
    public $baseUrl = '';

    /**
     * @var array request object configuration.
     */
    public $requestConfig = [];

    /**
     * @var array response config configuration.
     */
    public $responseConfig = [];

    /**
     * @var array response config configuration.
     */
    public $activeQueryClass = 'promocat\rest\ActiveQuery';

    /**
     * @var int Currently not used
     */
    public $defaultPerPage = 20;

    /**
     * @var int The maximum results requested per page when recursing
     */
    public $maxPerPage = 50;

    /**
     * @var bool Can retry requests when encountering certain response codes
     */
    public $allowRetries = false;

    /**
     * @var int The maximum ammount of retries that can be made for a single request
     */
    public $maxRetries = 1;

    /**
     * @var int The maximum total wait time between reties for the same request in milliseconds
     */
    public $maxWaitTimeBetweenRetries = 10000;

    /**
     * @var int Increase each interval between request retries by this amount in milliseconds
     */
    public $baseRetryInterval = 250;

    /**
     * @var int Interval multiplier increase between each retry.
     */
    public $increaseIntervalMultiplier = 2;

    /**
     * @var string|\Closure authorization config
     */
    protected $_auth = [];

    /**
     * @var \Closure Callback to test if API response has error
     * The function signature: `function ($response)`
     * Must return `null`, if the response does not contain an error.
     */
    protected $_errorChecker;

    /**
     * @var Response
     */
    protected $_response;

    /**
     * Returns the name of the DB driver. Based on the the current [[dsn]], in case it was not set explicitly
     * by an end user.
     *
     * @return string name of the DB driver
     */
    public static function getDriverName()
    {
        return 'rest';
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function init()
    {
        if (!$this->baseUrl) {
            throw new InvalidConfigException('The `baseUrl` config option must be set');
        }

        $this->baseUrl = rtrim($this->baseUrl, '/');

        parent::init();
    }

    /**
     * Returns the authorization config.
     *
     * @return string authorization config
     */
    public function getAuth()
    {
        if ($this->_auth instanceof \Closure) {
            $this->_auth = call_user_func($this->_auth, $this);
        }

        return $this->_auth;
    }

    /**
     * Changes the current authorization config.
     *
     * @param array $auth authorization config
     */
    public function setAuth($auth)
    {
        $this->_auth = $auth;
    }

    /**
     * Closes the connection when this component is being serialized.
     *
     * @return array
     */
    public function __sleep()
    {
        return array_keys(get_object_vars($this));
    }

    /**
     * Creates a command for execution.
     *
     * @param array $config the configuration for the Command class
     *
     * @return Command the DB command
     */
    public function createCommand($config = [])
    {
        $config['db'] = $this;
        $command = new Command($config);

        return $command;
    }

    /**
     * Creates new query builder instance.
     *
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        return new QueryBuilder($this);
    }

    /**
     * Returns the response object.
     *
     * @return null|Response
     */
    public function getResponse()
    {
        if (isset($this->_response)) {
            return $this->_response;
        }
        return null;
    }

    /**
     * Performs GET HTTP request.
     *
     * @param string $url URL
     * @param array $data request body
     *
     * @param array $headers
     * @return mixed response
     * @throws Exception
     */
    public function get($url, $data = [], $headers = [])
    {
        return $this->request('get', $url, $data, $headers);
    }

    /**
     * Handles the request with handler.
     * Returns array or raw response content, if $raw is true.
     *
     * @param string $method POST, GET, etc
     * @param string|array $url the URL for request, not including proto and site
     * @param array $data the request data
     *
     * @param array $headers
     *
     * @return Response|false
     * @throws Exception
     */
    protected function request($method, $url, $data = [], $headers = [])
    {
        $method = strtoupper($method);
        $profile = $method . ' ' . $url;
        Yii::beginProfile($profile, __METHOD__);
        $retry = false;
        $retries = 0;
        $retryWaitTime = 0;
        do {
            try {
                $this->_response = $this->_request($method, $url, $data, $headers);
            } catch (Exception $e) {
                if ($this->retryAllowed($e, $retries, $retryWaitTime)) {
                    $retries++;
                    $retry = true;
                    $interval = $this->calculateRetryInterval($e, $retries);
                    $retryWaitTime = $retryWaitTime + $interval;
                    usleep($interval * 1000);
                } else {
                    throw $e;
                }
            }
        } while ($retry === true);
        Yii::endProfile($profile, __METHOD__);
        if($this->_response !== false) {
            Yii::getLogger()->log($profile . ' STATUS ' . $this->_response->getStatusCode(), Logger::LEVEL_PROFILE,
                __METHOD__);
        }
        return $this->_response->data;
    }

    private function _request($method, $url, $data = [], $headers = [])
    {
        $response = call_user_func([$this->handler, $method], $url, $data, $headers)->send();
        /* @var RestResponse $response */
        if ($response->isOk) {
            return $response;
        }
        return false;
    }

    private function retryAllowed(RestException $e, int $retries, int $retryWaitTime)
    {
        $typeOfExceptionCanRetry = $e->canRetry();
        $newRetryWaitTime = $retryWaitTime + $this->calculateRetryInterval($e, $retries);
        return $typeOfExceptionCanRetry && $this->allowRetries && $retries < $this->maxRetries && $newRetryWaitTime < $this->maxWaitTimeBetweenRetries;
    }

    private function calculateRetryInterval(RestException $e, int $retries): int
    {
        $retryAfter = $e->getRetryAfter(false, true);
        $interval = $this->baseRetryInterval * ($this->increaseIntervalMultiplier ** ($retries -1));
        return $retryAfter !== null && $retryAfter > $interval ? $retryAfter : $interval;
    }

    /**
     * Performs HEAD HTTP request.
     *
     * @param string $url URL
     * @param array $data request body
     *
     * @param array $headers
     * @return HeaderCollection response
     * @throws Exception
     */
    public function head($url, $data = [], $headers = [])
    {
        $this->request('head', $url, $headers);
        return $this->_response->headers;
    }

    /**
     * Performs POST HTTP request.
     *
     * @param string $url URL
     * @param array $data request body
     *
     * @param array $headers
     * @return mixed response
     * @throws Exception
     */
    public function post($url, $data = [], $headers = [])
    {
        return $this->request('post', $url, $data, $headers);
    }

    /**
     * Performs PUT HTTP request.
     *
     * @param string $url URL
     * @param array $data request body
     *
     * @param array $headers
     * @return mixed response
     * @throws Exception
     */
    public function put($url, $data = [], $headers = [])
    {
        return $this->request('put', $url, $data, $headers);
    }

    /**
     * Performs DELETE HTTP request.
     *
     * @param string $url URL
     * @param array $data request body
     *
     * @param array $headers
     * @return mixed response
     * @throws Exception
     */
    public function delete($url, $data = [], $headers = [])
    {
        return $this->request('delete', $url, $data, $headers);
    }

    /**
     * Returns the request handler (Guzzle client for the moment).
     * Creates and setups handler if not set.
     *
     * @return Client
     */
    public function getHandler()
    {
        if (static::$_handler === null) {

            $requestConfig = array_merge([
                'class' => 'yii\httpclient\Request',
                'format' => Client::FORMAT_JSON,
            ], $this->requestConfig);

            $responseConfig = array_merge([
                'class' => 'promocat\rest\components\RestResponse',
                'format' => Client::FORMAT_JSON
            ], $this->responseConfig);

            static::$_handler = new Client([
                'baseUrl' => $this->baseUrl,
                'requestConfig' => $requestConfig,
                'responseConfig' => $responseConfig
            ]);
        }

        return static::$_handler;
    }
}
