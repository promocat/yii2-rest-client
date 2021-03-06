<?php

namespace promocat\rest\conditions;


use yii\db\conditions\NotCondition;
use yii\db\ExpressionInterface;

/**
 * {@inheritdoc}
 *
 * @property \promocat\rest\QueryBuilder $queryBuilder
 */
class NotConditionBuilder extends \yii\db\conditions\NotConditionBuilder
{
    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function build(ExpressionInterface $expression, array &$params = [])
    {
        /* @var $expression NotCondition */
        $operand = $expression->getCondition();
        if (empty($operand)) {
            return [];
        }

        $expression = $this->queryBuilder->buildCondition($operand, $params);

        return ['not' => $expression];
    }
}