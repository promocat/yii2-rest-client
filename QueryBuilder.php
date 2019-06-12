<?php

namespace promocat\rest;

use promocat\rest\conditions\BetweenConditionBuilder;
use promocat\rest\conditions\ConjunctionConditionBuilder;
use promocat\rest\conditions\HashConditionBuilder;
use promocat\rest\conditions\InConditionBuilder;
use promocat\rest\conditions\LikeConditionBuilder;
use promocat\rest\conditions\NotConditionBuilder;
use promocat\rest\conditions\SimpleConditionBuilder;
use yii\base\NotSupportedException;
use yii\db\Expression;
use yii\helpers\ArrayHelper;

/**
 * Class QueryBuilder builds an HiActiveResource query based on the specification given as a [[Query]] object.
 */
class QueryBuilder extends \yii\db\QueryBuilder
{

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
    public function __construct($connection, array $config = [])
    {
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
    public function build($query, $params = [])
    {
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

        $clauses = array_merge($clauses, $this->buildPagination($query));

        foreach ($clauses['filter'] as &$qp) {
            if (is_array($qp)) {
                foreach ($qp as &$value) {
                    if (is_array($value)) {
                        foreach ($value as &$_value) {
                            $_value = $params[$_value];
                        }
                    } elseif (isset($params[$value])) {
                        $value = $params[$value];
                    }
                }
            } else {
                if (isset($params[$qp])) {
                    $qp = $params[$qp];
                }
            }
        }

        return [
            'modelClass' => ArrayHelper::getValue($query, 'modelClass', ''),
            'uri' => $uri,
            'headers' => $headers,
            'queryParams' => array_filter($clauses, function ($value) {
                return !empty($value) || (int)$value === 0;
            }),
            'action' => $query->action
        ];
    }

    public function prepareQuery($query)
    {
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
     * @param $query
     *
     * @return string
     */
    public function buildUri($query)
    {
        if (!is_string($query->from)) {
            return '';
        }
        $uri = trim($query->from);
        if ($query->action) {
            return $uri;
        }
    }

    /**
     * This function is for you to provide your authentication.
     *
     * @param Query $query
     *
     * @return array
     */
    public function buildAuth($query)
    {
        $headers = [];
        $auth = $this->db->getAuth();
        if (!empty($auth)) {
            $headers['Authorization'] = $auth;
        }
        return $headers;
    }

    /**
     * @inheritdoc
     */
    public function buildSelect($columns, &$params, $distinct = false, $selectOptions = null)
    {
        if (!empty($columns) && is_array($columns)) {
            return implode($this->separator, $columns);
        }

        return '';
    }

    /**
     * @inheritdoc
     */
    public function buildJoin($joins, &$params)
    {
        if (empty($joins)) {
            return '';
        }
        $expand = [];
        foreach ($joins as $i => $join) {
            if (empty($join)) {
                continue;
            }
            if (is_array($join)) {
                foreach ((array)$join[1] as $attribute) {
                    $expandAttribute = explode(' ', $attribute);
                    $expand[] = reset($expandAttribute);
                }
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
    public function buildWhere($condition, &$params)
    {
        $where = $this->buildCondition($condition, $params);
        return $where;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function buildCondition($condition, &$params)
    {
        if ($condition instanceof Expression || empty($condition) || !is_array($condition)) {
            return [];
        }
        $condition = $this->createConditionFromArray($condition);
        /* @var $condition \yii\db\conditions\SimpleCondition */
        return $this->buildExpression($condition, $params);
    }

    /**
     * @inheritdoc
     */
    public function buildOrderBy($columns)
    {
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
     * @param Query $query
     * @return array the PER-PAGE and PAGE clauses
     */
    public function buildPagination($query)
    {
        $clauses = [];
        $perPage = null;
        $maxPerPage = $this->db->maxPerPage;

        if ($this->hasPerPage($query->perPage)) {
            $perPage = intval((string)($query->perPage > $maxPerPage ? $maxPerPage : $query->perPage));
        }
        if ($this->hasLimit($query->limit) && $query->limit < $maxPerPage) {
            $perPage = $query->limit;
        }

        if ($perPage !== null) {
            $clauses['per-page'] = $perPage;
        } else {
            $perPage = $this->db->defaultPerPage;
        }

        if ($this->hasOffset($query->offset)) {
            $offset = intval((string)$query->offset);
            $clauses['page'] = ceil($offset / $perPage) + 1;
            $clauses['per-page'] = $perPage; // When using offset, always use a per-page clause.
        }

        return $clauses;
    }

    /**
     * Checks to see if the given perPage is effective.
     * @param mixed $perPage the given page size
     * @return bool whether the perPage is effective
     */
    protected function hasPerPage($perPage)
    {
        return ($perPage instanceof ExpressionInterface) || ctype_digit((string)$perPage);
    }

    /**
     * @inheritdoc
     */
    public function buildHashCondition($condition, &$params)
    {
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

    /**
     * @return array
     */
    protected function defaultExpressionBuilders()
    {
        return [
            'yii\db\Query' => 'yii\db\QueryExpressionBuilder',
            'yii\db\PdoValue' => 'yii\db\PdoValueBuilder',
            'yii\db\Expression' => 'yii\db\ExpressionBuilder',
            'yii\db\conditions\ConjunctionCondition' => ConjunctionConditionBuilder::class,
            'yii\db\conditions\NotCondition' => NotConditionBuilder::class,
            'yii\db\conditions\AndCondition' => ConjunctionConditionBuilder::class,
            'yii\db\conditions\OrCondition' => ConjunctionConditionBuilder::class,
            'yii\db\conditions\BetweenCondition' => BetweenConditionBuilder::class,
            'yii\db\conditions\InCondition' => InConditionBuilder::class,
            'yii\db\conditions\LikeCondition' => LikeConditionBuilder::class,
//            'yii\db\conditions\ExistsCondition' => 'yii\db\conditions\ExistsConditionBuilder',
            'yii\db\conditions\SimpleCondition' => SimpleConditionBuilder::class,
            'yii\db\conditions\HashCondition' => HashConditionBuilder::class,
//            'yii\db\conditions\BetweenColumnsCondition' => 'yii\db\conditions\BetweenColumnsConditionBuilder'
        ];
    }
}
