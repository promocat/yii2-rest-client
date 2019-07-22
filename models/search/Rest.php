<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace promocat\rest\models\search;

use yii\data\ArrayDataProvider;
use yii\debug\components\search\Filter;
use yii\debug\models\search\Base;

/**
 * Search model for current REST calls.
 *
 * @author Brandon Tilstra <brandontilstra@promocat.nl>
 */
class Rest extends Base
{
    /**
     * @var string type of the input search value
     */
    public $type;
    /**
     * @var string query attribute input search value
     */
    public $query;
    /**
     * @var int status attribute input search value
     */
    public $status;


    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['type', 'query', 'status'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'type' => 'Type',
            'query' => 'Query',
            'status' => 'Status',
        ];
    }

    /**
     * Returns data provider with filled models. Filter applied if needed.
     *
     * @param array $models data to return provider for
     * @return ArrayDataProvider
     */
    public function search($models)
    {
        $dataProvider = new ArrayDataProvider([
            'allModels' => $models,
            'pagination' => false,
            'sort' => [
                'attributes' => ['duration', 'seq', 'type', 'query', 'duplicate', 'status'],
            ],
        ]);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $filter = new Filter();
        $this->addCondition($filter, 'type', true);
        $this->addCondition($filter, 'query', true);
        $this->addCondition($filter, 'status', true);
        $dataProvider->allModels = $filter->filter($models);

        return $dataProvider;
    }
}
