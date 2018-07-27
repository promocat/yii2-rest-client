<?php

namespace promocat\rest;

use yii\base\InvalidConfigException;
use yii\db\QueryInterface;
use yii\data\ActiveDataProvider;

/**
 * Class RestDataProvider
 */
class RestDataProvider extends ActiveDataProvider {
    /**
     * @var ActiveQuery the query that is used to fetch data models and [[totalCount]]
     * if it is not explicitly set.
     */
    public $query;

    /**
     * @inheritdoc
     */
    protected function prepareTotalCount() {
        if (!$this->query instanceof QueryInterface) {
            throw new InvalidConfigException('The "query" property must be an instance of a class that implements the QueryInterface e.g. yii\db\Query or its subclasses.');
        }

        return (int)$this->query->count();
    }
}
