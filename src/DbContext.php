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
    const CONFIG_CUSTOM_ASSERTIONS       = 'customAssertions';

    const PLATFORM_MAGENTO_ONE = 'magento';

    const PATH_LOCAL_XML = '/public/app/etc/local.xml';

    private static function assertDatabaseSettingsSectionExists(Environment $environment)
    {
        if ($environment->getSuite()->hasSetting(self::CONFIG_PARAMETERS)) {
            return;
        }

        $parameters = $environment->getSuite()->getSetting(self::CONFIG_PARAMETERS);

        if (isset($parameters[self::CONFIG_DATABASE_SETTINGS])) {
            return;
        }

        throw new \InvalidArgumentException(
            'There must be a parameters section of behat.yml containing your database settings.'
        );
    }

    private static function extractDatabaseSettings(Environment $environment)
    {
        $parameters = $environment->getSuite()->getSetting(self::CONFIG_PARAMETERS);

        return $parameters[self::CONFIG_DATABASE_SETTINGS];
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

    private static function assertRequiredDatabaseParametersExist(array $databaseSettings)
    {
        self::assertExists($databaseSettings, self::CONFIG_DATABASE_NAME);
        self::assertExists($databaseSettings, self::CONFIG_PATH_TO_SQL_DUMP);
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

    private static function executeCommand($command, $errorMsg)
    {
        $output = [];

        exec($command, $output, $status);

        if ($status !== 0) {
            $outputString = implode("\n", $output);

            throw new \RuntimeException(
                sprintf($errorMsg, $outputString)
            );
        }

        return $output;
    }

    private static function recreateDatabase($databaseName)
    {
        $dropCommand = "mysql -e 'DROP DATABASE IF EXISTS $databaseName'";

        self::executeCommand(
            $dropCommand,
            "An error occurred while dropping the current testing database '$databaseName':\n\n%s"
        );

        $createCommand = "mysql -e 'CREATE DATABASE $databaseName CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci'";

        self::executeCommand(
            $createCommand,
            "An error occurred while creating the new testing database '$databaseName':\n\n%s"
        );
    }

    private static function importDatabase($pathToSqlFile, $databaseName)
    {
        $command = "mysql $databaseName < $pathToSqlFile";

        self::executeCommand(
            $command,
            "An error occurred while importing the new testing database '$databaseName':\n\n%s"
        );
    }

    private static function executeCustomAssertion($databaseName, $sql)
    {
        $command = "mysql $databaseName -Ns -e \"$sql\"";

        $output = self::executeCommand(
            $command,
            "An error occurred while executing custom assertion '$sql':\n\n%s"
        );

        if (1 !== \count($output)) {
            throw new \RuntimeException(
                "Custom assertion '$sql' must use 'SELECT COUNT(*) ...'"
            );
        }

        $count = (int) $output[0];

        if (1 !== $count) {
            throw new \RuntimeException(
                "Custom assertion '$sql' has failed."
            );
        }
    }

    private static function importFreshTestingDatabase(array $databaseSettings)
    {
        if (! self::shouldImportTestingDatabase($databaseSettings)) {
            return;
        }

        $pathToSqlDump = $databaseSettings[self::CONFIG_PATH_TO_SQL_DUMP];
        $databaseName  = $databaseSettings[self::CONFIG_DATABASE_NAME];

        self::assertSqlDumpIsReadable($pathToSqlDump);

        self::recreateDatabase($databaseName);
        self::importDatabase($pathToSqlDump, $databaseName);

        echo 'Testing database has been imported successfully.';
    }

    private static function assertMagentoOneUsingTestingDatabase($projectRoot, $databaseName)
    {
        $localXml = simplexml_load_file($projectRoot . self::PATH_LOCAL_XML);

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

    private static function assertTestingDatabaseIsBeingUsed(array $databaseSettings)
    {
        $databaseName                 = $databaseSettings[self::CONFIG_DATABASE_NAME];
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

        while (true) {
            if (self::detectMagentoOnePlatform($searchPath)) {
                return [self::PLATFORM_MAGENTO_ONE, $searchPath];
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

    public static function assertCustomAssertionsPass(array $databaseSettings)
    {
        if (! isset($databaseSettings[self::CONFIG_CUSTOM_ASSERTIONS])) {
            return;
        }

        $databaseName = $databaseSettings[self::CONFIG_DATABASE_NAME];

        foreach ($databaseSettings[self::CONFIG_CUSTOM_ASSERTIONS] as $customAssertion) {
            self::executeCustomAssertion($databaseName, $customAssertion);
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

        self::assertDatabaseSettingsSectionExists($environment);

        $databaseSettings = self::extractDatabaseSettings($environment);

        self::assertRequiredDatabaseParametersExist($databaseSettings);
        self::assertTestingDatabaseIsBeingUsed($databaseSettings);

        self::importFreshTestingDatabase($databaseSettings);

        self::assertCustomAssertionsPass($databaseSettings);
    }
}