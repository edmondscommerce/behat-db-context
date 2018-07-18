# Behat DB Context
## By [Edmonds Commerce](https://www.edmondscommerce.co.uk)

A Behat context for managing your testing database.

### Installation

Install via composer

"edmondscommerce/behat-db-context": "dev-master@dev"


### Include Context in Behat Configuration

```
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

```
