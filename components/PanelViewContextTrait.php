<?php
/**
 * Created by PhpStorm.
 * User: Brandon Tilstra
 * Date: 22-7-2019
 * Time: 16:08
 */

namespace promocat\rest\components;

trait PanelViewContextTrait
{
    public function getViewPath()
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views';
    }
}