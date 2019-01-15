<?php

namespace promocat\rest;

/**
 * EmbeddedActiveRecord is an enhanced version of [[\promocat\rest\ActiveRecord]], which includes 'embedded' functionality.
 *
 * @see \promocat\rest\ActiveRecord
 *
 * @author Brandon Tilstra <brandontilstra@promocat.nl>
 * @author Paul Klimov <klimov.paul@gmail.com>
 */
class EmbeddedActiveRecord extends ActiveRecord implements ContainerInterface
{
    use ContainerTrait;

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
}
