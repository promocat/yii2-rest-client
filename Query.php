<?php

namespace promocat\rest;

use Yii;
use yii\db\QueryInterface;

/**
 * Class Query
 */
class Query extends \yii\db\Query implements QueryInterface
{
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
     * @var int limits the results returned per page. Not the same as $limit.
     */
    public $perPage;

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
    public function prepare($builder)
    {
        return $this;
    }

    /**
     * Creates a DB command that can be used to execute this query.
     *
     * @param Connection $db the connection used to generate the statement.
     * If this parameter is not given, the `rest` application component will be used.
     *
     * @param string $action
     *
     * @return Command the created DB command instance.
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     * @throws \yii\base\NotSupportedException
     */
    public function createCommand($db = null, $action = 'get')
    {
        if ($db === null) {
            $db = Yii::$app->get(Connection::getDriverName());
        }
        $this->addAction($action);
        $commandConfig = $db->getQueryBuilder()->build($this);

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
    public function count($q = '*', $db = null)
    {
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
    public function exists($db = null)
    {
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
    public function from($tables)
    {
        $this->from = $tables;

        return $this;
    }

    public function action($action)
    {
        $this->action = $action;

        return $this;
    }

    public function addAction($action)
    {
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
    public function column($db = null)
    {
        if ($this->emulateExecution) {
            return [];
        }

        $valueColumn = false;
        if ($this->indexBy !== null) {
            if (is_string($this->indexBy) && is_array($this->select) && count($this->select) === 1) {
                $valueColumn = reset($this->select);
                $this->select[] = $this->indexBy;
            }
        }
        $rows = $this->all($db);

        $results = [];
        if ($rows !== false) {
            foreach ($rows as $row) {
                if ($valueColumn === false || !isset($row[$valueColumn])) {
                    $value = reset($row);
                } else {
                    $value = $row[$valueColumn];
                }

                if ($this->indexBy === null) {
                    $results[] = $value;
                } elseif ($this->indexBy instanceof \Closure) {
                    $results[call_user_func($this->indexBy, $row)] = $value;
                } else {
                    $results[$row[$this->indexBy]] = $value;
                }
            }
        }
        return $results;
    }

    /**
     * Sets the per-page part of the query.
     * @param int
     * @return $this the query object itself
     */
    public function perPage($perPage)
    {
        $this->perPage = $perPage;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function one($db = null, $action = 'view')
    {
        if ($this->emulateExecution) {
            return false;
        }
        return $this->createCommand($db, $action)->queryOne();
    }

    /**
     * @inheritdoc
     */
    public function all($db = null)
    {
        if ($this->emulateExecution) {
            return [];
        }
        return iterator_to_array($this->each($batchSize, $db), true);
    }

    public function each($batchSize = 50, $db = null)
    {
        /*
         * Gets the maxPerPage setting from the db config. Defaults to 50 when not set.
         */
        if ($db === null) {
            $db = Yii::$app->get(Connection::getDriverName());
        }

        $maxPerPage = isset($db->maxPerPage) ? $db->maxPerPage : 50;
        $batchSize = $batchSize > $maxPerPage ? $maxPerPage : $batchSize;

        return Yii::createObject([
            'class' => BatchQueryResult::class,
            'query' => $this,
            'batchSize' => $batchSize,
            'db' => $db,
            'each' => true,
        ]);
    }

    public function batch($batchSize = 50, $db = null)
    {
        /*
         * Gets the maxPerPage setting from the db config. Defaults to 50 when not set.
         */
        if ($db === null) {
            $db = Yii::$app->get(Connection::getDriverName());
        }

        $maxPerPage = isset($db->maxPerPage) ? $db->maxPerPage : 50;
        $batchSize = $batchSize > $maxPerPage ? $maxPerPage : $batchSize;

        return Yii::createObject([
            'class' => BatchQueryResult::class,
            'query' => $this,
            'batchSize' => $batchSize,
            'db' => $db,
            'each' => false,
        ]);
    }

    private function buildBatchSize() {

    }
}
