<?php
/**
 * Created by PhpStorm.
 * User: tilst
 * Date: 17-9-2018
 * Time: 13:44
 */

namespace promocat\rest\components;

use yii\debug\panels\DbPanel;
use yii\log\Logger;

class RestPanel extends DbPanel
{
    public $db = 'rest';

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
}