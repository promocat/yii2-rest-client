<?php

namespace promocat\rest;

use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\db\BaseActiveRecord;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;
use Yii;

/**
 * Class ActiveRecord
 */
class ActiveRecord extends BaseActiveRecord {
    /**
     * @var boolean if in construction process (modifies behavior of hasAttribute method)
     */
    private $_isConstructing = false;

    /**
     * @var array attribute names indexed array of null values
     */
    private $_attributeNames = [];

    /**
     * Constructors.
     *
     * @param array $attributes the dynamic attributes (name-value pairs, or names) being defined
     * @param array $config the configuration array to be applied to this object.
     */
    public function __construct(array $attributes = [], $config = []) {
        $this->_isConstructing = true;
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
        $this->_isConstructing = false;
        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    public static function instantiate($row) {
        return new static($row);
    }

    /**
     * @inheritdoc
     */
    public function hasAttribute($name) {
        return $this->_isConstructing ? true : parent::hasAttribute($name);
    }

    /**
     * @inheritdoc
     */
    public function setAttribute($name, $value) {
        if ($this->hasAttribute($name)) {
            $this->_attributeNames[$name] = null;
        }
        parent::setAttribute($name, $value);
    }

    /**
     * @return null|Connection
     * @throws InvalidConfigException
     */
    public static function getDb() {
        $connection = Yii::$app->get(Connection::getDriverName());

        /* @var $connection Connection */
        return $connection;
    }

    /**
     * @inheritdoc
     *
     * @return ActiveQuery
     */
    public static function find($options = []) {
        $config = [
            'class' => 'promocat\rest\ActiveQuery',
            'options' => $options
        ];

        /* @var $query ActiveQuery */
        $query = Yii::createObject($config, [get_called_class()]);

        return $query;
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
    public static function modelName() {
        return Inflector::pluralize(Inflector::camel2id(StringHelper::basename(get_called_class()), '-'));
    }

    /**
     * Returns the primary key **name(s)** for this AR class. Defaults to ['id'].
     *
     * Note that an array should be returned even when the record only has a single primary key.
     *
     * For the primary key **value** see [[getPrimaryKey()]] instead.
     *
     * @return string[] the primary key name(s) for this AR class.
     */
    public static function primaryKey() {
        return ['id'];
    }

    /**
     * @inheritdoc
     */
    public function attributes() {
        return array_keys($this->_attributeNames);
    }

    /**
     * @inheritdoc
     */
    public function insert($runValidation = true, $attributes = null) {
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
    protected function insertInternal($attributes) {
        if (!$this->beforeSave(true)) {
            return false;
        }
        $values = $this->getDirtyAttributes($attributes);
        if (false === ($data = static::getDb()->createCommand()->insert(static::modelName(), $values))) {
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
     * @inheritdoc
     */
    public function update($runValidation = true, $attributeNames = null) {
        if ($runValidation && !$this->validate($attributeNames)) {
            Yii::info('Model not inserted due to validation error.', __METHOD__);

            return false;
        }

        return $this->updateInternal($attributeNames);
    }

    /**
     * @inheritdoc
     */
    protected function updateInternal($attributes = null) {
        if (!$this->beforeSave(false)) {
            return false;
        }
        $values = $this->getDirtyAttributes($attributes);
        if (empty($values)) {
            $this->afterSave(false, $values);

            return 0;
        }

        $command = static::getDb()->createCommand();
        $rows = $command->update(static::modelName(), $values, $this->getOldPrimaryKey(false));

        $changedAttributes = [];
        foreach ($values as $name => $value) {
            $changedAttributes[$name] = $this->getOldAttribute($name);
            $this->setOldAttribute($name, $value);
        }
        $this->afterSave(false, $changedAttributes);

        return $rows;
    }


    /**
     * @inheritdoc
     */
    public function delete() {
        $result = false;
        if ($this->beforeDelete()) {
            $command = static::getDb()->createCommand();
            $result = $command->delete(static::modelName(), $this->getOldPrimaryKey());

            $this->setOldAttributes(null);
            $this->afterDelete();
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function unlinkAll($name, $delete = false) {
        throw new NotSupportedException('unlinkAll() is not supported by RestClient, use unlink() instead.');
    }
}
