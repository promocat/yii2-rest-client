<?php

use promocat\rest\components\RestPanel;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\web\View;

/* @var RestPanel $panel */
/* @var yii\debug\models\search\Db $searchModel */
/* @var yii\data\ArrayDataProvider $dataProvider */
/* @var int[] $status */
/* @var int $sumDuplicates */

echo Html::tag('h1', $panel->getName() . ' Queries');

if ($sumDuplicates === 1) {
    echo "<p><b>$sumDuplicates</b> duplicated query found.</p>";
} elseif ($sumDuplicates > 1) {
    echo "<p><b>$sumDuplicates</b> duplicated queries found.</p>";
}

echo GridView::widget([
    'dataProvider' => $dataProvider,
    'id' => 'rest-panel-detailed-grid',
    'options' => ['class' => 'detail-grid-view table-responsive'],
    'filterModel' => $searchModel,
    'filterUrl' => $panel->getUrl(),
    'columns' => [
        [
            'attribute' => 'seq',
            'label' => 'Time',
            'value' => function ($data) {
                $timeInSeconds = $data['timestamp'] / 1000;
                $millisecondsDiff = (int)(($timeInSeconds - (int)$timeInSeconds) * 1000);

                return date('H:i:s.', $timeInSeconds) . sprintf('%03d', $millisecondsDiff);
            },
            'headerOptions' => [
                'class' => 'sort-numerical'
            ]
        ],
        [
            'attribute' => 'duration',
            'value' => function ($data) {
                return sprintf('%.1f ms', $data['duration']);
            },
            'options' => [
                'width' => '10%',
            ],
            'headerOptions' => [
                'class' => 'sort-numerical'
            ]
        ],
        [
            'attribute' => 'type',
            'value' => function ($data) {
                return Html::encode($data['type']);
            },
            'filter' => $panel->getTypes(),
        ],
        [
            'attribute' => 'duplicate',
            'label' => 'Duplicated',
            'options' => [
                'width' => '5%',
            ],
            'headerOptions' => [
                'class' => 'sort-numerical'
            ]
        ],
        [
            'attribute' => 'query',
            'value' => function ($data) use ($panel) {
                $query = Html::tag('div', Html::encode($data['query']));

                if (!empty($data['trace'])) {
                    $query .= Html::ul($data['trace'], [
                        'class' => 'trace',
                        'item' => function ($trace) use ($panel) {
                            return '<li>' . $panel->getTraceLine($trace) . '</li>';
                        },
                    ]);
                }

                return $query;
            },
            'format' => 'raw',
            'options' => [
                'width' => '60%',
            ],
        ],
        [
            'attribute' => 'status',
            'value' => function ($data) use ($status) {
                return $status[$data['query']] ?? null;
            },
            'options' => [
                'width' => '5%',
            ],
            'headerOptions' => [
                'class' => 'sort-numerical'
            ]
        ],
    ],
]);
?>
