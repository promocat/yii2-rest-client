<?php

namespace promocat\rest;

use yii\base\InvalidConfigException;
use yii\db\QueryInterface;
use yii\data\ActiveDataProvider;

/**
 * Class RestDataProvider
 */
class RestDataProvider extends ActiveDataProvider
{
    /**
     * @var ActiveQuery the query that is used to fetch data models and [[totalCount]]
     * if it is not explicitly set.
     */
    public $query;

    protected function prepareModels()
    {
        if (!$this->query instanceof QueryInterface) {
            throw new InvalidConfigException('The "query" property must be an instance of a class that implements the QueryInterface e.g. yii\db\Query or its subclasses.');
        }
        $query = clone $this->query;
        $pagination = $this->getPagination();

        if ($pagination !== false) {
            //Disable validatePage. The totalPageCount won't be available till after the query.
            $pagination->validatePage = false;
            $query->limit($pagination->getLimit())->offset($pagination->getOffset());
        }
        if (($sort = $this->getSort()) !== false) {
            $query->addOrderBy($sort->getOrders());
        }

        $command = $query->createCommand($this->db);
        $rows = $command->queryAll();
        if ($pagination !== false) {
            if (($response = $command->db->getResponse()) !== null) { //Get the response object from previous query.
                $pagination->totalCount = (int)$response->headers->get('x-pagination-total-count');
                $pagination->setPage((int)$response->headers->get('x-pagination-current-page') - 1,
                    $pagination->getPage());
                $pagination->setPageSize((int)$response->headers->get('x-pagination-per-page'),
                    $pagination->getPageSize());
                $this->setTotalCount($pagination->totalCount);
            }
            if ($pagination->totalCount === 0) {
                return [];
            }
        }

        return $query->populate($rows);
    }

    /**
     * @inheritdoc
     */
    protected function prepareTotalCount()
    {
        if (!$this->query instanceof QueryInterface) {
            throw new InvalidConfigException('The "query" property must be an instance of a class that implements the QueryInterface e.g. yii\db\Query or its subclasses.');
        }

        return (int)$this->query->count();
    }
}
