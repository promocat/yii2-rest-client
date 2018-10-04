<?php

namespace promocat\rest\conditions;

use yii\db\conditions\SimpleCondition;
use yii\db\ExpressionInterface;

/**
 * {@inheritdoc}
 *
 * @property \promocat\rest\QueryBuilder $queryBuilder
 */
class SimpleConditionBuilder extends \yii\db\conditions\SimpleConditionBuilder
{
    public $filterControls = [
        'AND' => 'and',
        'OR' => 'or',
        'NOT' => 'not',
        '<' => 'lt',
        '>' => 'gt',
        '<=' => 'lte',
        '>=' => 'gte',
        '=' => 'eq',
        '!=' => 'neq',
        'IN' => 'in',
        'NOT IN' => 'nin',
        'LIKE' => 'like'
    ];

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function build(ExpressionInterface $expression, array &$params = [])
    {
        /* @var $expression SimpleCondition */
        $operator = $expression->getOperator();
        $column = $expression->getColumn();
        $value = $expression->getValue();

        if ($value === null) {
            return [$column => [$this->filterControls[$operator] => null]];
        }
        if ($value instanceof ExpressionInterface) {
            return [
                $column => [
                    $this->filterControls[$operator] => $this->queryBuilder->buildExpression($value, $params)
                ]
            ];
        }

        $phName = $this->queryBuilder->bindParam($value, $params);
        return [$column => [$this->filterControls[$operator] => $phName]];
    }
}