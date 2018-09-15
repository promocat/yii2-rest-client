<?php

namespace promocat\rest;

use yii\base\InvalidConfigException;
use yii\db\ActiveQueryInterface;
use yii\db\ActiveQueryTrait;
use yii\db\ActiveRelationTrait;

/**
 * Class RestQuery
 */
class ActiveQuery extends Query implements ActiveQueryInterface {
    use ActiveQueryTrait;
    use ActiveRelationTrait;

    /**
     * @var array|null a list of relations that this query should be joined with
     */
    public $joinWith = [];

    /**
     * Constructor.
     *
     * @param string $modelClass the model class associated with this query
     * @param array $config configurations to be applied to the newly created query object
     */
    public function __construct($modelClass, $config = []) {
        $this->modelClass = $modelClass;
        parent::__construct($config);
    }


    /**
     * Creates a DB command that can be used to execute this query.
     *
     * @param Connection $db the DB connection used to create the DB command.
     *                       If null, the DB connection returned by [[modelClass]] will be used.
     *
     * @return Command the created DB command instance.
     */
    public function createCommand($db = null, $action = 'get') {
        /**
         * @var ActiveRecord $modelClass
         */
        $modelClass = $this->modelClass;

        if ($db === null) {
            $db = $modelClass::getDb();
        }

        if ($this->from === null) {
            $this->from($modelClass::modelName());
        }

//		if ($this->searchModel === null) {
//			$this->searchModel = mb_substr(mb_strrchr($this->modelClass, '\\'), 1).'Search';
//		}

        return parent::createCommand($db, $action);
    }

    /**
     * @inheritdoc
     *
     * @param bool $recurse Set to true, to really fetch all results spanning all pages!
     * @param null $db
     *
     * @return array
     */
    public function all($recurse = false, $db = null) {
        if ($recurse) {
            if ($this->emulateExecution) {
                return [];
            }
            $rows = $this->recurseAll($db);
            return $this->populate($rows);
        }
        return parent::all($db);
    }

    private function recurseAll($db, &$rows = null) {
        $command = $this->createCommand($db);

        if($rows === null) {
            $rows = $command->queryAll();
        } else {
            $rows = array_merge($rows, $command->queryAll());
        }

        /**
         * Get the response object
         */
        if (($response = $command->db->getResponse()) !== null) {
            $pageCount = (int)$response->headers->get('x-pagination-page-count');
            $currentPage = (int)$response->headers->get('x-pagination-current-page');

            if ($currentPage < $pageCount) { // We have not reached the end
                $perPage = (int)$response->headers->get('x-pagination-per-page');
                $this->offset($currentPage * $perPage);
                // Make another request!
                $this->recurseAll($db, $rows);
            }

        }
        return $rows;
    }

    public function indexBy($column, $unset = false) {
        $this->unsetIndexBy = $unset;
        return parent::indexBy($column);
    }

    /**
     * @inheritdoc
     */
    public function populate($rows) {
        if (empty($rows)) {
            return [];
        }

        $models = $this->createModels($rows);
        if (!empty($this->join) && $this->indexBy === null) {
            $models = $this->removeDuplicatedModels($models);
        }
        if (!empty($this->with)) {
            $this->findWith($this->with, $models);
        }
        if (!$this->asArray) {
            foreach ($models as $model) {
                $model->afterFind();
            }
        } elseif($this->indexBy !== null) {
            $result = [];
            foreach ($models as $model) {
                $index = ArrayHelper::getValue($model, $this->indexBy);
                if ($this->unsetIndexBy) {
                    unset($model[$this->indexBy]);
                }
                $result[$index] = $model;
            }
            $models = $result;
        }
        return $models;
    }

    /**
     * Removes duplicated models by checking their primary key values.
     * This method is mainly called when a join query is performed, which may cause duplicated rows being returned.
     *
     * @param array $models the models to be checked
     *
     * @throws InvalidConfigException if model primary key is empty
     * @return array the distinctive models
     */
    private function removeDuplicatedModels($models) {
        $hash = [];
        /* @var $class ActiveRecord */
        $class = $this->modelClass;
        $pks = $class::primaryKey();

        if (count($pks) > 1) {
            // composite primary key
            foreach ($models as $i => $model) {
                $key = [];
                foreach ($pks as $pk) {
                    if (!isset($model[$pk])) {
                        // do not continue if the primary key is not part of the result set
                        break 2;
                    }
                    $key[] = $model[$pk];
                }
                $key = serialize($key);
                if (isset($hash[$key])) {
                    unset($models[$i]);
                } else {
                    $hash[$key] = true;
                }
            }
        } elseif (empty($pks)) {
            throw new InvalidConfigException("Primary key of '{$class}' can not be empty.");
        } else {
            // single column primary key
            $pk = reset($pks);
            foreach ($models as $i => $model) {
                if (!isset($model[$pk])) {
                    // do not continue if the primary key is not part of the result set
                    break;
                }
                $key = $model[$pk];
                if (isset($hash[$key])) {
                    unset($models[$i]);
                } elseif ($key !== null) {
                    $hash[$key] = true;
                }
            }
        }

        return array_values($models);
    }

    /**
     * @inheritdoc
     */
    public function one($db = null, $action = 'view') {
        $row = parent::one($db, $action);
        if ($row !== false) {
            $models = $this->populate(isset($row[0]) ? $row : [$row]);

            return reset($models) ?: null;
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function prepare($builder = null) {
        if (!empty($this->joinWith)) {
            $this->buildJoinWith();
            $this->joinWith = null;
        }

        return $this;
    }

    /**
     * @param $with
     *
     * @return static
     */
    public function joinWith($with) {
        $this->joinWith[] = (array)$with;

        return $this;
    }

    private function buildJoinWith() {
        $join = $this->join;
        $this->join = [];

        $model = new $this->modelClass();

        foreach ($this->joinWith as $with) {
            $this->joinWithRelations($model, $with);
            foreach ($with as $name => $callback) {
                $this->innerJoin(is_int($name) ? $callback : [$name => $callback]);
                unset($with[$name]);
            }
        }

        if (!empty($join)) {
            // append explicit join to joinWith()
            // https://github.com/yiisoft/yii2/issues/2880
            $this->join = empty($this->join) ? $join : array_merge($this->join, $join);
        }

    }

    /**
     * @param ActiveRecord $model
     * @param $with
     */
    protected function joinWithRelations($model, $with) {
        foreach ($with as $name => $callback) {
            if (is_int($name)) {
                $name = $callback;
                $callback = null;
            }

            $primaryModel = $model;
            $parent = $this;

            if (!isset($relations[$name])) {
                $relations[$name] = $relation = $primaryModel->getRelation($name);
                if ($callback !== null) {
                    call_user_func($callback, $relation);
                }
                if (!empty($relation->joinWith)) {
                    $relation->buildJoinWith();
                }
                $this->joinWithRelation($parent, $relation);
            }
        }
    }

    /**
     * Joins a parent query with a child query.
     * The current query object will be modified accordingly.
     *
     * @param ActiveQuery $parent
     * @param ActiveQuery $child
     */
    private function joinWithRelation($parent, $child) {
        if (!empty($child->join)) {
            foreach ($child->join as $join) {
                $this->join[] = $join;
            }
        }
    }
}
