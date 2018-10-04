<?php

namespace promocat\rest;

use yii\base\Component;
use yii\helpers\Inflector;
use yii\helpers\ArrayHelper;

/**
 * Class Command class implements the API for accessing REST API.
 *
 * @property string $rawUrl The raw URL with parameter values inserted into the corresponding placeholders.
 * This property is read-only.
 */
class Command extends Component
{
    /**
     * @var Connection
     */
    public $db;

    /**
     * @var string the name of the ActiveRecord class.
     */
    public $modelClass;

    /**
     * @var string
     */
    public $action;

    /**
     * @var string
     */
    public $uri;

    /**
     * @var array
     */
    public $queryParams = [];

    /**
     * @var array
     */
    public $headers = [];

    /**
     * Returns the raw url by inserting parameter values into the corresponding placeholders.
     * Note that the return value of this method should mainly be used for logging purpose.
     * It is likely that this method returns an invalid URL due to improper replacement of parameter placeholders.
     * @return string the raw URL with parameter values inserted into the corresponding placeholders.
     */
    public function getRawUrl()
    {
        $rawUrl = $this->db->handler->get($this->pathInfo, $this->queryParams)->fullUrl;

        return $rawUrl;
    }

    /**
     * @return mixed
     */
    public function queryAll()
    {
        return $this->queryInternal();
    }

    /**
     * @return mixed
     */
    public function queryOne()
    {
        /* @var $class ActiveRecord */
        $class = $this->modelClass;

        if ($this->action === 'view' && !empty($class) && class_exists($class)) {
            $pks = $class::primaryKey();
            if (count($pks) === 1 && isset($this->queryParams['filter'])) {
                $primaryKey = current($pks);
                $primaryKeyValue = ArrayHelper::remove($this->queryParams['filter'], $primaryKey);
                if ($primaryKeyValue) {
                    $this->uri .= '/' . $primaryKeyValue;
                    $this->queryParams = array_filter($this->queryParams);
                }
            }
        }

        return $this->queryInternal();
    }

    /**
     * Make request and check for error.
     *
     * @param string $method
     *
     * @return mixed
     */
    public function execute($method = 'get')
    {
        return $this->queryInternal($method);
    }

    /**
     * Creates a new record
     *
     * @param string $uri
     * @param array $columns
     *
     * @return mixed
     */
    public function insert($uri, $columns)
    {
        if ($uri !== null) {
            $this->uri = $uri;
        }
        return $this->db->post($this->uri, $columns, $this->headers);
    }

    /**
     * Updates an existing record
     *
     * @param string $uri
     * @param array $data
     * @param string $id
     *
     * @return mixed
     */
    public function update($uri, $data = [], $id = null)
    {
        if ($uri !== null) {
            $this->uri = $uri;
        }
        if ($id) {
            $this->uri .= '/' . $id;
        }
        return $this->db->put($this->uri, $data, $this->headers);
    }

    /**
     * Deletes a record
     *
     * @param string $uri
     * @param string $id
     *
     * @return mixed
     */
    public function delete($uri, $id = null)
    {
        if ($uri !== null) {
            $this->uri = $uri;
        }
        if ($id) {
            $this->uri .= '/' . $id;
        }
        return $this->db->delete($this->uri, [], $this->headers);
    }

    /**
     * Performs the actual get statment
     *
     * @param string $method
     *
     * @return mixed
     */
    protected function queryInternal($method = 'get')
    {
        if (strpos($this->uri, '/') === false) {
            $this->uri = Inflector::pluralize($this->uri);
        }
        if (!empty($this->queryParams)) {
            $this->uri .= ('?' . http_build_query($this->queryParams));
            $this->queryParams = [];
        }
        return $this->db->$method($this->uri, $this->queryParams, $this->headers);
    }
}
