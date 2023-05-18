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
use SilverStripe\Security\DefaultAdminService;

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
        $envFile = filter_input(INPUT_ENV, 'DOCUMENT_ROOT') . '/../.env';
        if (self::$enabled === null || !file_exists($envFile)) {
            self::$config_helper = new Config();
            self::$enabled = self::$config_helper->isValidPlatform();
        }

        if (self::$enabled || !file_exists($envFile)) {
            self::set_credentials();
            self::update_platform_config();
        }
    }

    /**
     * Set up the database connection & default admin
     * @return void
     */
    private static function set_credentials()
    {
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
        try {
            // Force clearing and resetting the default admin/password, if they're set
            $vars = self::$config_helper->variables();
            if (
                !empty($vars['SS_DEFAULT_ADMIN_PASSWORD']) &&
                !empty($vars['SS_DEFAULT_ADMIN_USERNAME'])
            ) {
                DefaultAdminService::clearDefaultAdmin();
                DefaultAdminService::setDefaultAdmin(
                    $vars['SS_DEFAULT_ADMIN_USERNAME'],
                    $vars['SS_DEFAULT_ADMIN_PASSWORD']
                );
            }
        } catch (\Exception $e) {
            // No-op, ignore for now
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
        $current = Environment::getVariables();

        $new = array_merge($current['env'], $variables);
        Environment::setVariables($new);
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
            'Platform'     => $plVar,
            'IsEqual'      => hash_equals($ssVar, $plVar)
        ];
    }
}
