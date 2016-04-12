<?php

namespace app\database;

use \PDO;
use \PDOException;
use app\exceptions\DatabaseException;

/**
 * Class Database
 * @package lib
 */
class Database {

    /**
     * @var iQueries
     */
    private static $queries;

    /**
     * @var PDO
     */
    private static $link = null;

    /**
     * @var array
     */
    private static $results;

    /**
     * @var string
     */
    private static $lastid;

    /**
     * @var int
     */
    private static $query_count = 0;

    /**
     * @var array
     */
    private static $all_queries = array();

    /**
     * @var bool
     */
    private static $transaction = false;

    /**
     * Don't allow other classes to instantiate/copy Database (Singleton)
     */
    private function __construct() { }
    private function __clone() { }

    /**
     * Connect to a database via PDO
     *
     * @param string $host
     * @param string $user
     * @param string $password
     * @param string $name
     * @param string $type
     *
     * @throws DatabaseException
     */
    static function connect($host, $user, $password, $name, $type='pgsql') {
        // Only start a new connection if we're not already connected to a DB
        if (Database::$link == null) {
            Database::$query_count = 0;
            Database::$all_queries = array();

            if ($type == 'pgsql') {
                Database::$queries = new QueriesPostgresql();
            }
            else {
                throw new DatabaseException("Invalid database type '{$type}'");
            }

            try {
                Database::$link = new PDO("{$type}:host={$host};dbname={$name}", $user, $password);

                Database::$link->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                Database::$link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            catch (PDOException $pdoException) {
                throw new DatabaseException($pdoException->getMessage());
            }
        }
    }

    static function queries() {
        return static::$queries;
    }

    /**
     * Run a query against connected PDO link
     *
     * @param string $query
     * @param array $parameters
     *
     * @return boolean
     */
    static function query($query, $parameters=array()) {
        try {
            $statement = Database::$link->prepare($query);
            $statement->execute($parameters);
            Database::$results = $statement->fetchAll();
            Database::$lastid = Database::$link->lastInsertId();
            Database::$query_count++;
            Database::$all_queries[] = array($query, $parameters);
        }
        catch (PDOException $pdoException) {
            if (Database::$transaction) {
                Database::$link->rollBack();
                Database::$transaction = false;
            }
            throw new DatabaseException($pdoException->getMessage(), $query, $parameters);
        }

        return true;
    }

    /**
     * Start a DB transaction, turning off autocommit mode. Queries won't be
     * actually commited to the database till Database::commit() is called.
     */
    static function beginTransaction() {
        if (!Database::$transaction) {
            Database::$transaction = Database::$link->beginTransaction();
        }
    }

    /**
     * Actually commit/execute all queries to the database since we began the transaction.
     *
     * @throws \Exception
     */
    static function commit() {
        if (Database::$transaction) {
            Database::$link->commit();
            Database::$transaction = false;
        }
    }

    /**
     * Returns a single row from the result set from running a query. Removes the row
     * from the result set before returning it
     *
     * @return array|mixed
     */
    static function row() {
        if(Database::$results != NULL && count(Database::$results) > 0) {
            return array_shift(Database::$results);
        }
        else {
            return array();
        }
    }

    /**
     * Get all the rows (minus any gotten via Database::row() before calling
     * this function) from the last query
     *
     * @return array
     */
    static function rows() {
        if(Database::$results != NULL && count(Database::$results) > 0) {
            return Database::$results;
        }
        else {
            return array();
        }
    }

    /**
     * Return count of total queries run against current PDO connection
     *
     * @return int
     */
    static function totalQueries() {
        return Database::$query_count;
    }

    /**
     * Get all queries run against the current PDO connection
     *
     * @return string
     */
    static function getQueries() {
        $c = 1;
        $print = "";
        foreach(Database::$all_queries as $query) {
            $print .= ($c++).") ".$query[0];
            if (count($query[1]) > 0) {
                $print .= " --- ";
                foreach($query[1] as $parameter) {
                    $print .= "?".$parameter." ";
                }
            }
            $print .= "<br />";
        }
        return $print;
    }

    /**
     * "Disconnect" from current PDO connection by just setting
     * the link to null.
     */
    static function disconnect() {
        Database::$link = null;
        Database::$query_count = 0;
        Database::$all_queries = array();
    }

    /**
     * Returns true if we're connected to a database,
     * else return false
     *
     * @return bool
     */
    static function hasConnection() {
        return Database::$link !== null;
    }

    static function inTransaction() {
        return Database::$transaction;
    }

    static function getLastInsertId($name = "") {
        if (!empty($name)) {
            return Database::$link->lastInsertId($name);
        }
        else {
            return Database::$link->lastInsertId();
        }
    }
}
