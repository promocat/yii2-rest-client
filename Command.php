<?php

namespace promocat\rest;

use yii\base\Component;
use yii\helpers\Inflector;

/**
 * Class Command class implements the API for accessing REST API.
 *
 * @property string $rawUrl The raw URL with parameter values inserted into the corresponding placeholders.
 * This property is read-only.
 */
class Command extends Component {
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
     * @return mixed
     */
    public function queryAll() {
        return $this->queryInternal();
    }

    /**
     * @return mixed
     */
    public function queryOne() {
        /* @var $class ActiveRecord */
        $class = $this->modelClass;

        if (!empty($class) && class_exists($class)) {
            $pks = $class::primaryKey();

            if (count($pks) === 1 && isset($this->queryParams['filter'])) {
                $primaryKey = current($pks);
                if (isset($this->queryParams['filter'][$primaryKey])) {
                    $this->uri .= '/' . $this->queryParams['filter'][$primaryKey];
                    unset($this->queryParams['filter'][$primaryKey]);
                    $this->queryParams = array_filter($this->queryParams);
                }
            }
        }

        return $this->queryInternal();
    }

    /**
     * Performs the actual get statment
     *
     * @param string $method
     *
     * @return mixed
     */
    protected function queryInternal($method = 'get') {
        if (strpos($this->uri, '/') === false) {
            $this->uri = Inflector::pluralize($this->uri);
        }
        if (!empty($this->queryParams)) {
            $this->uri .= ('?' . http_build_query($this->queryParams));
            $this->queryParams = [];
        }
        return $this->db->$method($this->uri, $this->queryParams, $this->headers);
    }

    /**
     * Make request and check for error.
     *
     * @param string $method
     *
     * @return mixed
     */
    public function execute($method = 'get') {
        return $this->queryInternal($method);
    }

    /**
     * Creates a new record
     *
     * @param string $model
     * @param array $columns
     *
     * @return mixed
     */
    public function insert($model, $columns) {
        $this->uri = $model;

        return $this->db->post($this->uri, $columns, $this->headers);
    }

    /**
     * Updates an existing record
     *
     * @param string $model
     * @param array $data
     * @param string $id
     *
     * @return mixed
     */
    public function update($model, $data = [], $id = null) {
        $this->uri = $model;
        if ($id) {
            $this->uri .= '/' . $id;
        }

        return $this->db->put($this->uri, $data, $this->headers);
    }

    /**
     * Deletes a record
     *
     * @param string $model
     * @param string $id
     *
     * @return mixed
     */
    public function delete($model, $id = null) {
        $this->uri = $model;
        if ($id) {
            $this->uri .= '/' . $id;
        }

        return $this->db->delete($this->uri, [], $this->headers);
    }
}
