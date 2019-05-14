<?php

namespace promocat\rest;

use Yii;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\db\ActiveQueryInterface;
use yii\db\BaseActiveRecord;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;
use yii2tech\embedded\ContainerInterface;
use yii2tech\embedded\ContainerTrait;

/**
 * Class ActiveRecord
 *
 * ActiveRecord includes 'embedded' functionality from [\yii2tech\embedded\]
 *
 * @author Brandon Tilstra <brandontilstra@promocat.nl>
 */
class ActiveRecord extends BaseActiveRecord
{
    use ContainerTrait;

    /**
     * @var array Array of related records.
     */
    private $_relatedRecords = [];

    /**
     * Constructors.
     *
     * @param array $attributes the dynamic attributes (name-value pairs, or names) being defined
     * @param array $config the configuration array to be applied to this object.
     */
    public function __construct(array $attributes = [], $config = [])
    {
        $setOld = true;
        $keys = $this->primaryKey();
        foreach ($keys as $key) {
            if (!isset($attributes[$key])) {
                $setOld = false;
                break;
            }
        }
        foreach ($attributes as $name => $value) {
            if (is_int($name)) {
                $this->setAttribute($value, null);
            } else {
                $this->setAttribute($name, $value);
            }
        }
        if ($setOld) {
            $this->setOldAttributes($attributes);
        }
        parent::__construct($config);
    }

    /**
     * @inheritdoc
     * Defaults to ['id'].
     */
    public static function primaryKey()
    {
        return ['id'];
    }

    /**
     * @inheritdoc
     */
    public function setAttribute($name, $value)
    {
        try {
            parent::setAttribute($name, $value);
        } catch (InvalidArgumentException $e) {
            // do nothing
        }
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public static function populateRecord($record, $row)
    {
        /* @var $record static */
        parent::populateRecord($record, $row);
        $relatedRecords = $record->relatedRecords();
        foreach ($relatedRecords as $relationName => $name) {
            if (is_int($relationName)) {
                $relationName = $name;
            }
            if (isset($row[$name])) {
                $value = $row[$name];
                if ($record->canGetProperty($relationName)) {
                    $getter = 'get' . $relationName;
                    /** @var ActiveQuery $relation */
                    $relation = $record->$getter();
                    if ($relation instanceof ActiveQueryInterface) {
                        $models = $relation->modelClass::find()->populate($relation->multiple ? $value : [$value]);
                        $record->populateRelation($relationName, $relation->multiple ? $models : reset($models));
                    }
                }
            }
        }
    }

    /**
     * Returns an array of records that are related. The value should match the attribute name in the model.
     * When no key is supplied, the value will be used to find the relation. When a key is supplied, the key will be
     * used for this purpose.
     *
     * Example:
     * [
     *    'actors',
     *    'reviewers'
     *    'screenCaptures' => 'screen_captures'
     * ]
     *
     * @return array
     */
    public function relatedRecords()
    {
        return $this->_relatedRecords;
    }

    /**
     * @inheritdoc
     */
    public static function instantiate($row)
    {
        return new static($row);
    }

    /**
     * @inheritdoc
     *
     * @return ActiveQuery
     */
    public static function find()
    {
        return static::createQuery();
    }

    /**
     * @return ActiveQuery
     * @throws InvalidConfigException
     */
    private static function createQuery()
    {
        $class = static::getDb()->activeQueryClass;
        return new $class(get_called_class());
    }

    /**
     * @return null|Connection
     * @throws InvalidConfigException
     */
    public static function getDb()
    {
        $connection = Yii::$app->get(Connection::getDriverName());

        /* @var $connection Connection */
        return $connection;
    }

    /**
     * Declares the name of the url path associated with this AR class.
     * By default this method returns the class name as the path by calling [[Inflector::camel2id()]].
     * For example:
     * `Customer` becomes `customer`, and `OrderItem` becomes `order-item`. You may override this method
     * if the path is not named after this convention.
     *
     * @return string the url path
     */
    public static function modelName()
    {
        return Inflector::pluralize(Inflector::camel2id(StringHelper::basename(get_called_class()), '-'));
    }

    /**
     * @inheritdoc
     */
    public function insert($runValidation = true, $attributes = null)
    {
        if ($runValidation && !$this->validate($attributes)) {
            Yii::info('Model not inserted due to validation error.', __METHOD__);

            return false;
        }

        return $this->insertInternal($attributes);
    }

    /**
     * Inserts an ActiveRecord.
     *
     * @param array $attributes list of attributes that need to be saved. Defaults to `null`,
     * meaning all attributes that are loaded from DB will be saved.
     *
     * @return boolean whether the record is inserted successfully.
     */
    protected function insertInternal($attributes)
    {
        if (!$this->beforeSave(true)) {
            return false;
        }
        $values = $this->getDirtyAttributes($attributes);
        $command = static::createQuery()->createCommand(null, 'insert');
        if (false === ($data = $command->insert($command->uri, $values))) {
            return false;
        }
        foreach ($data as $name => $value) {
            $this->setAttribute($name, $value);
        }

        $changedAttributes = array_fill_keys(array_keys($values), null);
        $this->setOldAttributes($values);
        $this->afterSave(true, $changedAttributes);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $this->refreshFromEmbedded();
        return true;
    }

    /**
     * @inheritdoc
     */
    public function update($runValidation = true, $attributeNames = null)
    {
        if ($runValidation && !$this->validate($attributeNames)) {
            Yii::info('Model not inserted due to validation error.', __METHOD__);

            return false;
        }

        return $this->updateInternal($attributeNames);
    }

    /**
     * @inheritdoc
     */
    protected function updateInternal($attributes = null)
    {
        if (!$this->beforeSave(false)) {
            return false;
        }
        $values = $this->getDirtyAttributes($attributes);
        if (empty($values)) {
            $this->afterSave(false, $values);
            return 0;
        }
        $command = static::createQuery()->createCommand(null, 'update');
        $result = $command->update($command->uri, $values, $this->getOldPrimaryKey(false));
        // TODO: Maybe we should use the values in the response?
        $changedAttributes = [];
        foreach ($values as $name => $value) {
            $changedAttributes[$name] = $this->getOldAttribute($name);
            $this->setOldAttribute($name, $value);
        }
        $this->afterSave(false, $changedAttributes);

        return $result !== false ? 1 : 0;
    }


    /**
     * @inheritdoc
     */
    public function delete()
    {
        $result = false;
        if ($this->beforeDelete()) {
            $command = static::createQuery()->createCommand(null, 'delete');
            $result = $command->delete($command->uri, $this->getOldPrimaryKey());

            $this->setOldAttributes(null);
            $this->afterDelete();
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function unlinkAll($name, $delete = false)
    {
        throw new NotSupportedException('unlinkAll() is not supported by RestClient, use unlink() instead.');
    }
}
