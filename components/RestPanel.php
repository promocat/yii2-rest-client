<?php
/**
 * Created by PhpStorm.
 * User: tilst
 * Date: 17-9-2018
 * Time: 13:44
 */

namespace promocat\rest\components;

use promocat\rest\models\search\Rest;
use Yii;
use yii\base\ViewContextInterface;
use yii\debug\panels\DbPanel;
use yii\log\Logger;

class RestPanel extends DbPanel implements ViewContextInterface
{
    use PanelViewContextTrait;

    public $db = 'rest';

    public function getViewPath()
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'REST';
    }

    /**
     * @return string short name of the panel, which will be use in summary.
     */
    public function getSummaryName()
    {
        return 'REST';
    }

    /**
     * Returns all profile logs of the current request for this panel. It includes categories such as:
     * 'yii\db\Command::query', 'yii\db\Command::execute'.
     *
     * @return array
     */
    public function getProfileLogs()
    {
        $target = $this->module->logTarget;

        return $target->filterMessages($target->messages, Logger::LEVEL_PROFILE, ['promocat\rest\Connection::request']);
    }

    public function getSummary()
    {
        $timings = $this->calculateTimings();
        $queryCount = count($timings);
        $queryTime = number_format($this->getTotalQueryTime($timings) * 1000) . ' ms';

        return Yii::$app->view->render('panels/rest/summary', [
            'timings' => $this->calculateTimings(),
            'panel' => $this,
            'queryCount' => $queryCount,
            'queryTime' => $queryTime,
        ], $this);
    }

    /**
     * {@inheritdoc}
     */
    public function getDetail()
    {
        $status = [];
        foreach ($this->data['messages'] as $message) {
            if ($message[1] !== Logger::LEVEL_PROFILE) {
                continue;
            }
            preg_match('/^([a-zA-Z]+\s.+)\sSTATUS\s(\d+)$/', $message[0], $matches);
            if (!empty($matches)) {
                $status[$matches[1]] = $matches[2];
            }
        }
        $searchModel = new Rest();

        if (!$searchModel->load(Yii::$app->request->getQueryParams())) {
            $searchModel->load($this->defaultFilter, '');
        }

        $models = $this->getModels();
        $dataProvider = $searchModel->search($models);
        $dataProvider->getSort()->defaultOrder = $this->defaultOrder;
        $sumDuplicates = $this->sumDuplicateQueries($models);

        return Yii::$app->view->render('panels/rest/detail', [
            'panel' => $this,
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
            'hasExplain' => $this->hasExplain(),
            'sumDuplicates' => $sumDuplicates,
            'status' => $status
        ], $this);
    }
}