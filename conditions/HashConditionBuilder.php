<?php

namespace promocat\rest\conditions;


use promocat\rest\Query;
use yii\db\ExpressionInterface;
use yii\helpers\ArrayHelper;

/**
 * {@inheritdoc}
 *
 * @property \promocat\rest\QueryBuilder $queryBuilder
 */
class HashConditionBuilder extends \yii\db\conditions\HashConditionBuilder
{
    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function build(ExpressionInterface $expression, array &$params = [])
    {
        /* @var $expression \yii\db\conditions\HashCondition */

        $hash = $expression->getHash();
        $parts = [];
        foreach ($hash as $column => $value) {
            if (ArrayHelper::isTraversable($value) || $value instanceof Query) {
                // IN condition
//                $parts[] = $this->queryBuilder->buildCondition(new InCondition($column, 'IN', $value), $params);
                $parts[$column] = ['in' => $value];
            } else {
                if ($value === null) {
                    $parts[$column] = [$column => null];
                } elseif ($value instanceof ExpressionInterface) {
                    $parts[$column] = [$column => $this->queryBuilder->buildExpression($value, $params)];
                } else {
                    $phName = $this->queryBuilder->bindParam($value, $params);
                    $parts[$column] = $phName;
                }
            }
        }
        return $parts;
//        return count($parts) === 1 ? reset($parts) : $parts;
    }
}