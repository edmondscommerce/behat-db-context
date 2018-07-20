# Behat DB Context
## By [Edmonds Commerce](https://www.edmondscommerce.co.uk)

A Behat context for managing your testing database.

### Installation

Install via composer

"edmondscommerce/behat-db-context": "dev-master@dev"


### Include Context in Behat Configuration

```yaml
default:
    # ...
    suites:
        default:
        # ...
            contexts:
                - # ...
                - EdmondsCommerce\BehatDbContext\DbContext
            parameters:
              databaseSettings:
                importTestingDatabase: true
                databaseName: some_testing_db
                pathToSqlDump: '../some_testing_db.sql'
                customAssertions:
                  # Must be wrapped in double quotes
                  - "SELECT COUNT(*) FROM some_table WHERE some_thing = some_value"
                  - "SELECT COUNT(*) FROM some_table WHERE another_thing = another_value"
```

### Features

#### Ensures You're Using the Testing Database

The context employs platform detection to ensure that your application is currently using
the test database you configured in `behat.yml` using `databaseName`.

If you're not using the correct database then you'll receive an exception and the tests won't
proceed.

To add support for you're platform you can follow the steps detailed below in the
'Adding Support For New Platforms' section.

#### Import Fresh Testing Database

The context imports a fresh version of the testing database each time you run your test
suite. It imports the SQL dump configured in your `behat.yml` using `pathToSqlDump` and
imports this into the database `databaseName`.

NOTE: this context assumes you have your MYSQL credentials configured in `.my.cnf`.

#### Optional Testing Database Import

When `importTestingDatabase` is set to `false` the import step will be skipped. This is useful
while working on the tests locally but should always be set to `true` when finally running
the test suite.

Even when this is set to `false` the check to confirm you're using the correct database
and your custom assertions (see below) will still be run to ensure everything is
configured correctly.

#### Custom Assertions

In order to flexibly confirm the database is in the correct state the context supports
custom assertions. These are simple `COUNT` SQL queries which need to return `1` in order
to pass. You can provide as many of these as you like.

For example:

```sql
SELECT COUNT(*) FROM core_config_data WHERE path = 'web/unsecure/base_url' AND value = 'https://www.base.url.com/'
```

### Adding Support For New Platforms

All platform code is contained within the [Platform](src/Util/Platform.php) class. In order to extend
this you need to provide two functions; one which handles platform detection and one which handles
database detection.

#### Platform Detection

##### Add Detection Method

You need to add a method that can detect the platform from the project root. The Magento platform detection
simply does this by looking for `local.xml`:

```php
    // ...

    const MAGENTO_ONE_PATH_TO_LOCAL_XML = '/public/app/etc/local.xml';

    private static function detectMagentoOnePlatform($projectRoot)
    {
        return is_file($projectRoot . self::MAGENTO_ONE_PATH_TO_LOCAL_XML);
    }
    
    // ...
```

##### Add Method to Detect

You then need to add this to the detect method:

```php
    // ...
    
    public static function detect()
    {
        // ...

        while (true) {
            if (self::detectMagentoOnePlatform($searchPath)) {
                return [self::MAGENTO_ONE, $searchPath];
            }

            // ...

            $searchPath = realpath($searchPath . '/../');

            if ($searchPath === false) {
                break;
            }
        }

        throw new \RuntimeException('Failed finding project root.');
    }
    
    // ...
```

And add your platform as a constant:

```php
// ...

    const MAGENTO_ONE = 'magento';
    
// ...
```

#### Database Detection

Now that you have platform detection in place you need to handle database configuration detection.

##### Add Assertion Method

You need to add a method that can assert that the platforms database is currently configured to use
the testing database. If the platform isn't configured correctly it should throw an exception.

Here's the Magento 1 assertion for example:

```php
    // ...
    
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
    
    // ...
```

##### Add Method to Assert

You then need to add this platform specific assertion to the generic assertion method:

```php
    // ...
    
    public static function assertTestingDatabaseIsBeingUsed($databaseName)
    {
        list($platform, $projectRoot) = self::detect();

        switch ($platform) {
            case self::MAGENTO_ONE:
                self::assertMagentoOneUsingTestingDatabase($projectRoot, $databaseName);
                break;
            // Add your platform specific assertion here...
        }
    }
    
    // ...
```

Your platform is now supported!