<?php

namespace EdmondsCommerce\BehatDbContext\Util;

use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;

class Settings
{
    const SETTING_PARAMETERS              = 'parameters';
    const SETTING_DATABASE_SETTINGS       = 'databaseSettings';
    const SETTING_IMPORT_TESTING_DATABASE = 'importTestingDatabase';
    const SETTING_PATH_TO_SQL_DUMP        = 'pathToSqlDump';
    const SETTING_DATABASE_NAME           = 'databaseName';
    const SETTING_CUSTOM_ASSERTIONS       = 'customAssertions';

    /**
     * @var bool
     */
    private $importTestingDatabase;

    /**
     * @var string
     */
    private $pathToSqlDump;

    /**
     * @var string
     */
    private $databaseName;

    /**
     * @var string[]
     */
    private $customAssertions;

    public function __construct(BeforeSuiteScope $event)
    {
        $databaseSettings = $this->extractSettings($event);

        $this->importTestingDatabase = $this->extractImportTestingDatabase($databaseSettings);
        $this->pathToSqlDump         = $databaseSettings[self::SETTING_PATH_TO_SQL_DUMP];
        $this->databaseName          = $databaseSettings[self::SETTING_DATABASE_NAME];
        $this->customAssertions      = $this->extractCustomAssertions($databaseSettings);
    }

    private function assertDatabaseSettingsSectionExists(Environment $environment)
    {
        if ($environment->getSuite()->hasSetting(self::SETTING_PARAMETERS)) {
            return;
        }

        $parameters = $environment->getSuite()->getSetting(self::SETTING_PARAMETERS);

        if (isset($parameters[self::SETTING_DATABASE_SETTINGS])) {
            return;
        }

        throw new \InvalidArgumentException(
            'There must be a parameters section of behat.yml containing your database settings.'
        );
    }

    private function extractDatabaseSettings(Environment $environment)
    {
        $parameters = $environment->getSuite()->getSetting(self::SETTING_PARAMETERS);

        return $parameters[self::SETTING_DATABASE_SETTINGS];
    }

    private function extractCustomAssertions($databaseSettings)
    {
        if (isset($databaseSettings[self::SETTING_CUSTOM_ASSERTIONS])) {
            return $databaseSettings[self::SETTING_CUSTOM_ASSERTIONS];
        }

        return [];
    }

    private function extractImportTestingDatabase(array $databaseSettings)
    {
        if (isset($databaseSettings[self::SETTING_IMPORT_TESTING_DATABASE])) {
            return (bool) $databaseSettings[self::SETTING_IMPORT_TESTING_DATABASE];
        }

        return true;
    }

    private function assertParameterExists(array $parameters, $parameter)
    {
        if (isset($parameters[$parameter])) {
            return;
        }

        throw new \InvalidArgumentException(
            "You must set '$parameter' within the database settings in behat.yml."
        );
    }

    private function assertRequiredDatabaseParametersExist(array $databaseSettings)
    {
        $this->assertParameterExists($databaseSettings, self::SETTING_DATABASE_NAME);
        $this->assertParameterExists($databaseSettings, self::SETTING_PATH_TO_SQL_DUMP);
    }

    public function extractSettings(BeforeSuiteScope $event)
    {
        $environment = $event->getEnvironment();

        $this->assertDatabaseSettingsSectionExists($environment);

        $databaseSettings = $this->extractDatabaseSettings($environment);

        $this->assertRequiredDatabaseParametersExist($databaseSettings);

        return $databaseSettings;
    }

    /**
     * @return bool
     */
    public function shouldImportTestingDatabase()
    {
        return $this->importTestingDatabase;
    }

    /**
     * @return string
     */
    public function getPathToSqlDump()
    {
        return $this->pathToSqlDump;
    }

    /**
     * @return string
     */
    public function getDatabaseName()
    {
        return $this->databaseName;
    }

    /**
     * @return string[]
     */
    public function getCustomAssertions()
    {
        return $this->customAssertions;
    }
}