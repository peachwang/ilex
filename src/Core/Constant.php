<?php

namespace Ilex\Core;

use \Ilex\Core\Loader;
use \Ilex\Lib\Kit;

/**
 * Class Constant
 * The class in charge of initializing const variables.
 * @package Ilex\Core
 *
 * @property private static array $constantMapping
 * 
 * @method final public static initialize()
 */
final class Constant
{
    private static $constantMapping = [
        /*
         * -----------------------
         * Server
         * -----------------------
         */
        'SVR_MONGO_HOST'    => 'localhost',
        'SVR_MONGO_PORT'    => 27017,
        'SVR_MONGO_USER'    => 'admin',
        'SVR_MONGO_PASS'    => 'admin',
        'SVR_MONGO_TIMEOUT' => 2000,
        'SVR_MONGO_DB'      => 'test',
    ];

    public static function initialize()
    {
        $constantMapping = require_once(Loader::APPPATH() . 'Config/Const.php');
        Kit::update(self::$constantMapping, $constantMapping);
        $constantMapping = require_once(Loader::APPPATH() . 'Config/Const-local.php');
        Kit::update(self::$constantMapping, $constantMapping);
        foreach (self::$constantMapping as $name => $value) {
            if (FALSE === defined($name)) define($name, $value);
        }
    }
}