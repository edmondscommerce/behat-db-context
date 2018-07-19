<?php

namespace EdmondsCommerce\BehatDbContext\Util;

class Command
{
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

    public static function recreateDatabase($databaseName)
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

    public static function importDatabase($pathToSqlFile, $databaseName)
    {
        $command = "mysql $databaseName < $pathToSqlFile";

        self::executeCommand(
            $command,
            "An error occurred while importing the new testing database '$databaseName':\n\n%s"
        );
    }

    public static function executeCustomAssertion($databaseName, $sql)
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
}