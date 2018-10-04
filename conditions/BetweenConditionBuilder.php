<?php

namespace promocat\rest\conditions;

use yii\db\conditions\BetweenCondition;
use yii\db\ExpressionInterface;

/**
 * {@inheritdoc}
 *
 * @property \promocat\rest\QueryBuilder $queryBuilder
 */
class BetweenConditionBuilder extends \yii\db\conditions\BetweenConditionBuilder
{
    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function build(ExpressionInterface $expression, array &$params = [])
    {
        /* @var $expression BetweenCondition */
        $operator = $expression->getOperator();
        $column = $expression->getColumn();

        $phName1 = $this->createPlaceholder($expression->getIntervalStart(), $params);
        $phName2 = $this->createPlaceholder($expression->getIntervalEnd(), $params);

        if ($operator === 'BETWEEN') {
            return [
                $column => [
                    'gt' => $phName1,
                    'lt' => $phName2
                ]
            ];
        } else {
            return $this->queryBuilder->buildCondition([
                'or',
                ['<', $column, $expression->getIntervalStart()],
                ['>', $column, $expression->getIntervalEnd()]
            ], $params);
        }
    }
}