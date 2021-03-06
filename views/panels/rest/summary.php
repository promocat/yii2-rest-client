<?php

use promocat\rest\components\RestPanel;

/* @var RestPanel $panel */
/* @var integer $queryCount */
/* @var integer $queryTime */

if ($queryCount): ?>
    <div class="yii-debug-toolbar__block">
        <a href="<?= $panel->getUrl() ?>"
           title="Executed <?= $queryCount ?> REST calls which took <?= $queryTime ?>.">
            <?= $panel->getSummaryName() ?> <span
                    class="yii-debug-toolbar__label yii-debug-toolbar__label_info"><?= $queryCount ?></span>
            <span class="yii-debug-toolbar__label"><?= $queryTime ?></span>
        </a>
    </div>
<?php endif; ?>
