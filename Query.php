<?php

namespace promocat\rest;

use yii\db\QueryInterface;
use Yii;

/**
 * Class Query
 */
class Query extends \yii\db\Query implements QueryInterface {

    /**
     * @var string action that this query performs
     */
    public $action;

    /**
     * @var string the model to be selected from
     * @see from()
     */
    public $from;

    /**
     * @var ActiveRecord
     */
    public $searchModel;

    /**
     * Prepares for building query.
     * This method is called by [[QueryBuilder]] when it starts to build SQL from a query object.
     * You may override this method to do some final preparation work when converting a query into a SQL statement.
     *
     * @param QueryBuilder $builder
     *
     * @return $this a prepared query instance which will be used by [[QueryBuilder]] to build the SQL
     */
    public function prepare($builder) {
        return $this;
    }

    /**
     * Creates a DB command that can be used to execute this query.
     *
     * @param Connection $db the connection used to generate the statement.
     * If this parameter is not given, the `rest` application component will be used.
     *
     * @return Command the created DB command instance.
     */
    public function createCommand($db = null, $action = 'get') {
        if ($db === null) {
            $db = Yii::$app->get(Connection::getDriverName());
        }
        $commandConfig = $db->getQueryBuilder()->build($this->addAction($action));

        return $db->createCommand($commandConfig);
    }

    /**
     * Returns the number of records.
     *
     * @param string $q the COUNT expression. Defaults to '*'.
     * @param Connection $db the database connection used to execute the query.
     * If this parameter is not given, the `db` application component will be used.
     *
     * @return int number of records.
     */
    public function count($q = '*', $db = null) {
        if ($this->emulateExecution) {
            return 0;
        }
        $result = $this->createCommand($db, 'count')->execute('head');

        /* @var $result \yii\web\HeaderCollection */

        return $result->get('x-pagination-total-count');
    }

    /**
     * @inheritdoc
     */
    public function exists($db = null) {
        if ($this->emulateExecution) {
            return false;
        }

        $result = $this->createCommand($db, 'exists')->execute('head');

        /* @var $result \yii\web\HeaderCollection */
        return ($result->get('x-pagination-total-count', 0) > 0);
    }

    /**
     * Sets the model to read from / write to
     *
     * @param string $tables
     *
     * @return $this the query object itself
     */
    public function from($tables) {
        $this->from = $tables;

        return $this;
    }

    public function action($action) {
        $this->action = $action;

        return $this;
    }

    public function addAction($action) {
        if (empty($this->action)) {
            $this->action = $action;
        }

        return $this;
    }

    /**
     * Executes the query and returns the first column of the result.
     * Order of indexBy() and select() is now irrelevant.
     *
     * @param Connection $db the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     *
     * @return array the first column of the query result. An empty array is returned if the query results in nothing.
     */
    public function column($db = null) {
        if ($this->emulateExecution) {
            return [];
        }

        if ($this->indexBy === null) {
            return $this->createCommand($db)->queryColumn();
        }
        $valueColumn = false;
        if (is_string($this->indexBy) && is_array($this->select) && count($this->select) === 1) {
            $valueColumn = reset($this->select);
            if (strpos($this->indexBy, '.') === false && count($tables = $this->getTablesUsedInFrom()) > 0) {
                $this->select[] = key($tables) . '.' . $this->indexBy;
            } else {
                $this->select[] = $this->indexBy;
            }
        }
        $rows = $this->createCommand($db)->queryAll();
        $results = [];

        foreach ($rows as $row) {
            if ($valueColumn === false || !isset($row[$valueColumn])) {
                $value = reset($row);
            } else {
                $value = $row[$valueColumn];
            }
            if ($this->indexBy instanceof \Closure) {
                $results[call_user_func($this->indexBy, $row)] = $value;
            } else {
                $results[$row[$this->indexBy]] = $value;
            }
        }

        return $results;
    }
}
