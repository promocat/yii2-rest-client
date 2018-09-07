<?php

namespace promocat\rest;

use yii\base\InvalidArgumentException;
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
        try {
            parent::setAttribute($name, $value);
        } catch (InvalidArgumentException $e) {
            // do nothing
        }
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
    public static function find() {
        return static::createQuery();
    }

    /**
     * @return ActiveQuery
     * @throws InvalidConfigException
     */
    private static function createQuery() {
        $class = static::getDb()->activeQueryClass;
        return new $class(get_called_class());
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
    public function delete() {
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
    public function unlinkAll($name, $delete = false) {
        throw new NotSupportedException('unlinkAll() is not supported by RestClient, use unlink() instead.');
    }
}
