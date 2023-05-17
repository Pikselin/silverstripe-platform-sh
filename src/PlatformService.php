<?php

namespace Pikselin\Platform;

use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\BaseKernel;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\CoreKernel;
use SilverStripe\Core\Environment;
use SilverStripe\Core\EnvironmentLoader;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Kernel;
use SilverStripe\ORM\DB;
use Platformsh\ConfigReader\Config;

/**
 * @class Pikselin\Platform\PlatformService
 *
 * Helper class to load platform.sh variables in to Silverstripe Environment
 */
class PlatformService
{
    /**
     * @var bool
     */
    private static $enabled;

    /**
     * @var Config
     */
    private static $config_helper;

    /**
     * @throws NotFoundExceptionInterface
     */
    public static function init()
    {
        // Only run if there is no .env file
        $envFile = filter_input(INPUT_ENV, 'DOCUMENT_ROOT') . '/.env';
        if (self::$enabled === null || !file_exists($envFile)) {
            self::$config_helper = new Config();
            self::$enabled = self::$config_helper->isValidPlatform();
        }

        if (self::$enabled || !file_exists($envFile)) {
            self::update_platform_config();
        }
    }

    /**
     * Set up the database connection
     * @return void
     */
    public static function set_db()
    {
        if (!self::$config_helper) {
            self::init();
        }
        if (!self::$enabled) {
            return;
        }
        try {
            $credentials = self::$config_helper->credentials('database');

            /**
             * Override Database configuration for Platform.sh
             * This needs to run against DB, and not from the Environment variables.
             */
            DB::setConfig([
                'server'   => $credentials['host'],
                'username' => $credentials['username'],
                'password' => $credentials['password'],
                'database' => $credentials['path'],
                'type'     => 'MySQLDatabase'
            ]);
        } catch (\Exception $e) {
            //no-op, ignore platform complaining
        }
    }

    /**
     * Set up the variables from PLATFORM_VARIABLES environment
     * @return void
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    private static function update_platform_config()
    {
        $variables = self::$config_helper->variables();
        self::buildEnv($variables);
    }

    private static function buildEnv($env)
    {
        foreach ($env as $key => $value) {
            try {
                $newenv = sprintf("%s=%s\n", $key, $value);
                putenv($newenv);
            } catch (\Exception $e) {
                // no-op, putenv failed, continue to the next
            }
        }
    }

    /**
     * Check if a variable is what is expected from it.
     *
     * @param $var
     * @return array|void
     */
    public static function test_variable($var)
    {
        if (!self::$enabled) {
            return;
        }
        $ssVar = Environment::getEnv($var);
        $plVar = self::$config_helper->variable($var);
        return [
            'Silverstripe' => $ssVar,
            'Platform' => $plVar,
            'IsEqual' => hash_equals($ssVar, $plVar)
        ];
    }
}
