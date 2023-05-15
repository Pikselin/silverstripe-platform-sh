<?php

namespace Pikselin\Platform;

use SilverStripe\Core\BaseKernel;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\CoreKernel;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Kernel;
use SilverStripe\ORM\DB;
use Platformsh\ConfigReader\Config;

class PlatformService
{
    use Configurable;

    /**
     * @var bool
     */
    private static $enabled;

    /**
     * @var Config
     */
    private static $config_helper;

    /**
     * @var array
     */
    protected static $env_variables;

    public static function init()
    {
        if (self::$enabled === null) {
            self::$config_helper = new Config();
            self::$enabled = self::$config_helper->isValidPlatform();
        }

        if (self::$enabled) {
            self::$env_variables = self::config()->get('env_variables');
            self::update_platform_config();
        }
    }

    private static function update_platform_config(){

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

        /**
         * Insert admin vars.
         */
        $variables = self::$config_helper->variables();

        $current = Environment::getVariables();

        /** @var CoreKernel $kernel */
        $kernel = Injector::inst()->get(Kernel::class);

        if ($variables) {
            // Default to LIVE
            $kernel->setEnvironment($variables['SS_ENVIRONMENT_TYPE'] ?? BaseKernel::LIVE);

            // Run through the variables from platform.sh and remove those
            // That we don't want, or Silverstripe doesn't use.
            // This is to prevent any potential accidental information leakage
            foreach ($variables as $key => $value) {
                if (!in_array(self::$env_variables, $key)) {
                    unset($variables[$key]);
                }
            }
            // Shove EVERYTHING together and make it the new environment
            $current['env'] = array_merge($current['env'], $variables);
            Environment::setVariables($current);
        }
    }
}
