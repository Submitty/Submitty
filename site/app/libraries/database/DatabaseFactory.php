<?php

namespace app\libraries\database;

use app\exceptions\DatabaseException;
use app\exceptions\NotImplementedException;
use app\libraries\Core;

/**
 * Class DatabaseFactory
 *
 * Factory class that we can use to contain the logic for what database provider and queries classes
 * we should use given some driver, so that it's not contained within Core and allows for a bit of
 * separation of concerns and smaller functions and easier unit testing around the DB infrastructure.
 *
 * When adding new drivers to the system, we should keep in mind the ways people may reference
 * what they think their driver is and how it would map to the PDO driver internally. Examples of
 * this would be that Postgresql is referred to (by PDO) as pgsql, but people generally call it
 * either postgresql or postgres so we allow for either of those to equate to pgsql to allow for
 * easier time. MySQL would additionally allow for the mapping of say mariadb or percona as
 * those are popular drop-in replacements for MySQL, but people may not realize the driver would
 * just be called mysql.
 */
class DatabaseFactory {
    private $driver;

    /**
     * DatabaseFactory constructor.
     *
     * @param string $driver driver to use for getting the instantiated AbstractDatabase class
     *                       as well as appropriate query class.
     */
    public function __construct($driver) {
        $this->driver = strtolower($driver);
    }

    /**
     * Get an instantiated AbstractDatabase instance setting it up with the connection parameters that we
     * pass in. See the concrete database class you're interested in using to see what the accepted
     * connection parameters for it.
     *
     * @param array $connection_params array of connection parameters that are specific for the database
     *
     * @return AbstractDatabase
     *
     * @throws NotImplementedException if you attempt to use a driver that is not recognized
     */
    public function getDatabase($connection_params) {
        switch ($this->driver) {
            case 'pgsql':
            case 'postgres':
            case 'postgresql':
                return new PostgresqlDatabase($connection_params);
                break;
            case 'sqlite':
                return new SqliteDatabase($connection_params);
                break;
            default:
                throw new NotImplementedException("Driver not implemented: {$this->driver}");
        }
    }

    /**
     * Get the DatabaseQueries class to use for the given driver. By default, any driver should be
     * able to use the DatabaseQueries class, but for some we have provider specific implementations
     * that we can use.
     *
     * @param \app\libraries\Core $core
     *
     * @return DatabaseQueries
     */
    public function getQueries(Core $core) {
        switch ($this->driver) {
            case 'pgsql':
            case 'postgres':
            case 'postgresql':
                return new PostgresqlDatabaseQueries($core);
                break;
            default:
                return new DatabaseQueries($core);
        }

    }
}
