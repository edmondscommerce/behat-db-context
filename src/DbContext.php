<?php

namespace EdmondsCommerce\BehatDbContext;

use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Behat\Testwork\Environment\Environment;
use EdmondsCommerce\BehatDbContext\Util\Command;
use EdmondsCommerce\BehatDbContext\Util\Platform;
use EdmondsCommerce\BehatDbContext\Util\Settings;

class DbContext extends RawMinkContext
{
    private static function assertSqlDumpIsReadable($pathToSqlDump)
    {
        if (file_exists($pathToSqlDump) && is_readable($pathToSqlDump)) {
            return;
        }

        throw new \InvalidArgumentException(
            "The provided SQL dump '$pathToSqlDump' is not readable."
        );
    }

    private static function importFreshTestingDatabase(Settings $databaseSettings)
    {
        if (! $databaseSettings->shouldImportTestingDatabase()) {
            return;
        }

        $pathToSqlDump = $databaseSettings->getPathToSqlDump();
        $databaseName  = $databaseSettings->getDatabaseName();

        self::assertSqlDumpIsReadable($pathToSqlDump);

        Command::recreateDatabase($databaseName);
        Command::importDatabase($pathToSqlDump, $databaseName);

        echo 'Testing database has been imported successfully.';
    }

    public static function assertCustomAssertionsPass(Settings $databaseSettings)
    {
        $databaseName = $databaseSettings->getDatabaseName();

        foreach ($databaseSettings->getCustomAssertions() as $customAssertion) {
            Command::executeCustomAssertion($databaseName, $customAssertion);
        }
    }

    /**
     * @BeforeSuite
     *
     * @param BeforeSuiteScope $event
     */
    public static function setupTestingDatabase(BeforeSuiteScope $event)
    {
        $databaseSettings = new Settings($event);

        Platform::assertTestingDatabaseIsBeingUsed(
            $databaseSettings->getDatabaseName()
        );

        self::importFreshTestingDatabase($databaseSettings);

        self::assertCustomAssertionsPass($databaseSettings);
    }
}