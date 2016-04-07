<?php
namespace Dag;

use Symfony\Component\Yaml\Yaml;

/**
 * Class Settings
 * @package Dag
 */
class Settings
{
    private static $yaml;

    public static function value($key)
    {
        if (!self::$yaml) {
            if (!getenv('DAG_CLIENT_ENV')) putenv("DAG_CLIENT_ENV=defaults");

            self::$yaml = Yaml::parse(file_get_contents(__DIR__.'/../../config/settings.yml'));
        }
        return @self::$yaml[getenv('DAG_CLIENT_ENV')][$key];
    }
}
