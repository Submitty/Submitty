<?php

namespace app\libraries\database;


use app\exceptions\DatabaseException;
use app\exceptions\NotImplementedException;
use app\libraries\Core;

class DatabaseFactory {
    private $driver;

    public function __construct($driver) {
        $this->driver = $driver;
    }

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

    public function getQueries(Core $core) {
        switch ($this->driver) {
            case 'pgsql':
            case 'postgres':
            case 'postgresql':
                return new PostgresqlDatabaseQueries($core);
                break;
            case 'sqlite':
                return new SqliteDatabaseQueries($core);
                break;
            default:
                throw new NotImplementedException("Driver not implemented: {$this->driver}");
        }

    }
}
