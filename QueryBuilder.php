<?php

namespace promocat\rest;

use yii\db\Expression;
use yii\base\NotSupportedException;
use yii\helpers\ArrayHelper;

/**
 * Class QueryBuilder builds an HiActiveResource query based on the specification given as a [[Query]] object.
 */
class QueryBuilder extends \yii\db\QueryBuilder {

    /**
     * @var Connection the database connection.
     */
    public $db;

    /**
     * @var string the separator between different fragments of a SQL statement.
     * Defaults to an empty space. This is mainly used by [[build()]] when generating a SQL statement.
     */
    public $separator = ',';

    /**
     * @var array the abstract column types mapped to physical column types.
     * This is mainly used to support creating/modifying tables using DB-independent data type specifications.
     * Child classes should override this property to declare supported type mappings.
     */
    public $typeMap = [];

    /**
     * @var array map of query condition to builder methods.
     * These methods are used by [[buildCondition]] to build SQL conditions from array syntax.
     */
    protected $conditionBuilders = [
        'AND' => 'buildAndCondition',
    ];

    /**
     * QueryBuilder constructor.
     *
     * @param mixed $connection the database connection.
     * @param array $config name-value pairs that will be used to initialize the object properties
     */
    public function __construct($connection, array $config = []) {
        parent::__construct($connection, $config);
    }

    /**
     * Build query data
     *
     * @param Query $query
     * @param array $params
     *
     * @return array
     * @throws NotSupportedException
     */
    public function build($query, $params = []) {
        $query = $query->prepare($this);

        $params = empty($params) ? $query->params : array_merge($params, $query->params);

        $query = $this->prepareQuery($query);

        $uri = $this->buildUri($query);

        $headers = $this->buildAuth($query);

        $clauses = [
            'fields' => $this->buildSelect($query->select, $params),
            'expand' => $this->buildJoin($query->join, $params),
            'filter' => $this->buildWhere($query->where, $params),
            'sort' => $this->buildOrderBy($query->orderBy)
        ];
        $clauses = array_merge($clauses, $this->buildLimit($query->limit, $query->offset));

        return [
            'modelClass' => ArrayHelper::getValue($query, 'modelClass', ''),
            'uri' => $uri,
            'headers' => $headers,
            'queryParams' => array_filter($clauses),
            'action' => $query->action
        ];
    }

    public function buildUri($query) {
        if (!is_string($query->from)) {
            return '';
        }
        $uri = trim($query->from);
        if ($query->action)
            return $uri;
    }

    public function prepareQuery($query) {
        if (empty($query->where)) {
            $query->where = [];
        }
        if (isset($query->primaryModel)) {
            foreach ($query->link as $filterAttribute => $valueAttribute) {
                if (isset($query->primaryModel->{$valueAttribute})) {
                    $query->where([$filterAttribute => $query->primaryModel->{$valueAttribute}]);
                }
            }
        }
        return $query;
    }

    /**
     * This function is for you to provide your authentication.
     *
     * @param Query $query
     */
    public function buildAuth($query) {
        $headers = [];
        $auth = $this->db->getAuth();
        if (isset($auth['headerToken'])) {
            $headers['Authorization'] = 'token ' . $auth['headerToken'];
        }
        if (isset($auth['headerBearer'])) {
            $headers['Authorization'] = 'Bearer ' . $auth['headerBearer'];
        }
        return $headers;
    }

    /**
     * @inheritdoc
     */
    public function buildSelect($columns, &$params, $distinct = false, $selectOptions = null) {
        if (!empty($columns) && is_array($columns)) {
            return implode($this->separator, $columns);
        }

        return '';
    }

    /**
     * @param string $tables
     * @param array $params the binding parameters to be populated
     *
     * @return string the model name
     */
    public function buildFrom($tables, &$params) {
        if (!is_string($tables)) {
            return '';
        }

        return trim($tables);
    }

    /**
     * @inheritdoc
     */
    public function buildJoin($joins, &$params) {
        if (empty($joins)) {
            return '';
        }

        $expand = [];
        foreach ($joins as $i => $join) {
            if (empty($join)) {
                continue;
            }
            if (is_array($join)) {
                $expand[] = $join[1];
                continue;
            }
            $expand[] = $join;
        }

        return implode($this->separator, $expand);
    }

    /**
     * @param string|array $condition
     * @param array $params the binding parameters to be populated
     *
     * @return array the WHERE clause built from [[Query::$where]].
     */
    public function buildWhere($condition, &$params) {
        $where = $this->buildCondition($condition, $params);
        return $where;
    }

    public function buildLink(&$query, &$params) {
        if (empty($query->where)) {
            $query->where = [];
        }
        if (isset($query->primaryModel)) {
            foreach ($query->link as $filterAttribute => $valueAttribute) {
                if (isset($params[$valueAttribute])) {
                    $query->where([$filterAttribute => $params[$valueAttribute]]);
                } elseif (isset($query->primaryModel->{$valueAttribute})) {
                    $query->where([$filterAttribute => $query->primaryModel->{$valueAttribute}]);
                }
            }
        }
        return $query->where;
    }

    /**
     * @inheritdoc
     */
    public function buildOrderBy($columns) {
        if (empty($columns)) {
            return '';
        }

        $orders = [];
        foreach ($columns as $name => $direction) {
            if ($direction instanceof Expression) {
                $orders[] = $direction->expression;
            } else {
                $orders[] = ($direction === SORT_DESC ? '-' : '') . $name;
            }
        }

        return implode($this->separator, $orders);
    }

    /**
     * @param integer $limit
     * @param integer $offset
     *
     * @return array the LIMIT and OFFSET clauses
     */
    public function buildLimit($limit, $offset) {
        $clauses = [];
        if ($this->hasLimit($limit)) {
            $clauses['per-page'] = (string)$limit;
        }
        if ($this->hasOffset($offset)) {
            $offset = intval((string)$offset);
            $clauses['page'] = ceil($offset / $limit) + 1;
        }

        return $clauses;
    }

    /**
     * @param $condition
     * @param $params
     *
     * @return array|string
     * @throws NotSupportedException
     */
    public function buildCondition($condition, &$params) {
        if ($condition instanceof Expression || empty($condition) || !is_array($condition)) {
            return [];
        }

        if (isset($condition[0])) { // operator format: operator, operand 1, operand 2, ...
            var_dump($condition);
            /*
            $operator = strtoupper($condition[0]);
            if (!isset($this->conditionBuilders[$operator])) {
                throw new NotSupportedException($operator.' is not supported.');
            }
            $method = $this->conditionBuilders[$operator];
            array_shift($condition);

            return $this->$method($operator, $condition, $params);
            /*/
            return [];
            //*/
        } else { // hash format: 'column1' => 'value1', 'column2' => 'value2', ...
            return $this->buildHashCondition($condition, $params);
        }
    }

    /**
     * @inheritdoc
     */
    public function buildHashCondition($condition, &$params) {
        $parts = [];
        foreach ($condition as $attribute => $value) {
            if (is_array($value)) { // IN condition
                continue;
            } else {
                $parts[$attribute] = str_replace(array_keys($params), array_values($params), $value);
            }
        }

        return $parts;
    }
}
