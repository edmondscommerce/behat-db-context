<?php

namespace EdmondsCommerce\BehatDbContext;

use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Behat\Testwork\Environment\Environment;

class DbContext extends RawMinkContext
{
    const CONFIG_PARAMETERS              = 'parameters';
    const CONFIG_DATABASE_SETTINGS       = 'databaseSettings';
    const CONFIG_IMPORT_TESTING_DATABASE = 'importTestingDatabase';
    const CONFIG_PATH_TO_SQL_DUMP        = 'pathToSqlDump';
    const CONFIG_DATABASE_NAME           = 'databaseName';

    const PLATFORM_MAGENTO_ONE = 'magento';

    const PATH_LOCAL_XML = '/public/app/etc/local.xml';

    private static function assertParametersSectionExists(Environment $environment)
    {
        if ($environment->getSuite()->hasSetting(self::CONFIG_PARAMETERS)) {
            return;
        }

        throw new \InvalidArgumentException(
            'There must be a parameters section of behat.yml containing your database settings.'
        );
    }

    private static function extractParameters(Environment $environment)
    {
        return $environment->getSuite()->getSetting(self::CONFIG_PARAMETERS);
    }

    private static function shouldImportTestingDatabase(array $parameters)
    {
        if (isset($parameters[self::CONFIG_IMPORT_TESTING_DATABASE])) {
            return (bool) $parameters[self::CONFIG_IMPORT_TESTING_DATABASE];
        }

        return true;
    }

    private static function assertExists(array $parameters, $parameter)
    {
        if (isset($parameters[$parameter])) {
            return;
        }

        throw new \InvalidArgumentException(
            "You must set '$parameter' within the database settings in behat.yml."
        );
    }

    private static function assertRequiredDatabaseParametersSet(array $parameters)
    {
        self::assertExists($parameters, self::CONFIG_DATABASE_NAME);
        self::assertExists($parameters, self::CONFIG_PATH_TO_SQL_DUMP);
    }

    private static function assertSqlDumpIsReadable($pathToSqlDump)
    {
        if (file_exists($pathToSqlDump) && is_readable($pathToSqlDump)) {
            return;
        }

        throw new \InvalidArgumentException(
            "The provided SQL dump '$pathToSqlDump' is not readable."
        );
    }

    private static function dropDatabase($databaseName)
    {
        $command = "mysql -e 'DROP DATABASE IF EXISTS $databaseName'";
        $output  = [];

        exec($command, $output, $status);

        if ($status !== 0) {
            $outputString = implode("\n", $output);

            throw new \RuntimeException(
                "An error occurred while dropping the current testing database '$databaseName':\n\n$outputString"
            );
        }
    }

    private static function importDatabase($pathToSqlFile, $databaseName)
    {
        $command = "mysql $databaseName < $pathToSqlFile";
        $output  = [];

        exec($command, $output, $status);

        if ($status !== 0) {
            $outputString = implode("\n", $output);

            throw new \RuntimeException(
                "An error occurred while importing the new testing database '$databaseName':\n\n$outputString"
            );
        }
    }

    private static function importFreshTestingDatabase(array $parameters)
    {
        if (! self::shouldImportTestingDatabase($parameters)) {
            return;
        }

        echo 'Importing clean testing database.';

        $pathToSqlDump = $parameters[self::CONFIG_PATH_TO_SQL_DUMP];
        $databaseName  = $parameters[self::CONFIG_DATABASE_NAME];

        self::assertSqlDumpIsReadable($pathToSqlDump);

        self::dropDatabase($databaseName);
        self::importDatabase($pathToSqlDump, $databaseName);

        echo 'Testing database has been imported successfully.';
    }

    private static function assertMagentoOneUsingTestingDatabase($projectRoot, $databaseName)
    {
        $localXml             = file_get_contents($projectRoot . self::PATH_LOCAL_XML);
        $containsDatabaseName = preg_match("#\<dbname\>\<!\[CDATA\[$databaseName\]\]\>\</dbname\>#", $localXml);

        if (1 === $containsDatabaseName) {
            return;
        }

        throw new \InvalidArgumentException(
            "You need to configure Magento to use the testing database '$databaseName'"
        );
    }

    private static function assertTestingDatabaseIsBeingUsed(array $parameters)
    {
        $databaseName                 = $parameters[self::CONFIG_DATABASE_NAME];
        list($platform, $projectRoot) = self::detectPlatform();

        switch ($platform) {
            case self::PLATFORM_MAGENTO_ONE:
                self::assertMagentoOneUsingTestingDatabase($projectRoot, $databaseName);
                break;
            // Add your platform code here for checking which database is being used...
        }
    }

    private static function detectMagentoOnePlatform($projectRoot)
    {
        return is_file($projectRoot . self::PATH_LOCAL_XML);
    }

    private static function detectMagentoTwoPlatform($projectRoot)
    {
        return is_file($projectRoot . '/bin/magento');
    }

    private static function detectLaravelPlatform($projectRoot)
    {
        return is_file($projectRoot . '/artisan');
    }

    private static function detectPlatform()
    {
        $searchPath = __DIR__;
        $platform   = null;

        while ($platform === null) {
            if (self::detectMagentoOnePlatform($searchPath)) {
                return [$platform, $searchPath];
            }

            if (self::detectMagentoTwoPlatform($searchPath)) {
                throw new \RuntimeException('Magento 2 detected. This is currently not supported.');
            }

            if (self::detectLaravelPlatform($searchPath)) {
                throw new \RuntimeException('Laravel detected. This is currently not supported.');
            }

            $searchPath = realpath($searchPath . '/../');

            if ($searchPath === false) {
                throw new \RuntimeException('Failed finding project root.');
            }
        }
    }

    /**
     * @BeforeSuite
     *
     * @param BeforeSuiteScope $event
     */
    public static function setupTestingDatabase(BeforeSuiteScope $event)
    {
        $environment = $event->getEnvironment();

        self::assertParametersSectionExists($environment);

        $parameters = self::extractParameters($environment);

        self::assertRequiredDatabaseParametersSet($parameters);
        self::assertTestingDatabaseIsBeingUsed($parameters);

        self::importFreshTestingDatabase($parameters);
    }
}