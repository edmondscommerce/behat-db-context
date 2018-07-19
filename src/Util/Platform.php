<?php

namespace EdmondsCommerce\BehatDbContext\Util;

class Platform
{
    const MAGENTO_ONE = 'magento';

    const MAGENTO_ONE_PATH_TO_LOCAL_XML = '/public/app/etc/local.xml';

    private static function detectMagentoOnePlatform($projectRoot)
    {
        return is_file($projectRoot . self::MAGENTO_ONE_PATH_TO_LOCAL_XML);
    }

    private static function detectMagentoTwoPlatform($projectRoot)
    {
        return is_file($projectRoot . '/bin/magento');
    }

    private static function detectLaravelPlatform($projectRoot)
    {
        return is_file($projectRoot . '/artisan');
    }

    public static function detect()
    {
        $searchPath = __DIR__;
        $platform   = null;

        while (true) {
            if (self::detectMagentoOnePlatform($searchPath)) {
                return [self::MAGENTO_ONE, $searchPath];
            }

            if (self::detectMagentoTwoPlatform($searchPath)) {
                throw new \RuntimeException('Magento 2 detected. This is currently not supported.');
            }

            if (self::detectLaravelPlatform($searchPath)) {
                throw new \RuntimeException('Laravel detected. This is currently not supported.');
            }

            $searchPath = realpath($searchPath . '/../');

            if ($searchPath === false) {
                break;
            }
        }

        throw new \RuntimeException('Failed finding project root.');
    }

    private static function assertMagentoOneUsingTestingDatabase($projectRoot, $databaseName)
    {
        $localXml = simplexml_load_string(
            file_get_contents($projectRoot . Platform::MAGENTO_ONE_PATH_TO_LOCAL_XML)
        );

        if (! isset($localXml->global->resources->default_setup->connection->dbname)) {
            throw new \RuntimeException(
                'You need to configure a dbname in your local.xml'
            );
        }

        if ((string) $localXml->global->resources->default_setup->connection->dbname === $databaseName) {
            return;
        }

        throw new \InvalidArgumentException(
            "You need to configure Magento to use the testing database '$databaseName' in local.xml"
        );
    }

    public static function assertTestingDatabaseIsBeingUsed($databaseName)
    {
        list($platform, $projectRoot) = self::detect();

        switch ($platform) {
            case self::MAGENTO_ONE:
                self::assertMagentoOneUsingTestingDatabase($projectRoot, $databaseName);
                break;
            // Add your platform code here for checking which database is being used...
        }
    }
}