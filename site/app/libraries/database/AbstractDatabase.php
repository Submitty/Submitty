<?php

namespace app\libraries\database;

use \PDO;
use \PDOException;

use app\exceptions\DatabaseException;
use app\libraries\Utils;

abstract class AbstractDatabase {

    /**
     * @var PDO
     */
    protected $link = null;

    /**
     * @var array
     */
    protected $results = array();

    protected $row_count = 0;

    /**
     * @var int
     */
    protected $query_count = 0;

    /**
     * @var array
     */
    protected $all_queries = array();

    /**
     * @var bool
     */
    protected $transaction = false;

    protected $username = null;
    protected $password = null;

    /**
     * Database constructor. This function (overridden in all children) sets our
     * connection parameters for when we connect. Due to the sensitive nature of the
     * parameters of this function and that we never want to leak these either to the
     * user or in the logs, this should never throw an exception, and we'll just let
     * PDO throw an exception when we attempt to connect with a broken DSN.
     *
     * @param array $connection_params
     */
    public function __construct($connection_params) {
        if (isset($connection_params['username'])) {
            $this->username = $connection_params['username'];
        }
        if (isset($connection_params['password'])) {
            $this->password = $connection_params['password'];
        }
    }

    abstract protected function getDSN();

    /**
     * Given a string representation of an array from the database, convert it to a PHP
     * array.
     * @param string $array
     *
     * @return array
     */
    abstract public function fromDatabaseArrayToPHP($array);
    abstract public function fromPHPArrayToDatabase($array);

    /**
     * Connects to a database through the PDO extension (@link http://php.net/manual/en/book.pdo.php).
     * We wrap the potential exception that would get thrown by the PDO constructor so that we can
     * bubble up the message, without exposing any of the parameters used by the connect function
     * as we don't wany anyone to get the DB details.
     *
     * @throws DatabaseException
     */
    public function connect() {
        // Only start a new connection if we're not already connected to a DB
        if ($this->link === null) {
            $this->query_count = 0;
            $this->all_queries = array();

            try {
                if (isset($this->username) && isset($this->password)) {
                    $this->link = new PDO($this->getDSN(), $this->username, $this->password);
                }
                else if (isset($this->username)) {
                    $this->link = new PDO($this->getDSN(), $this->username);
                }
                else {
                    $this->link = new PDO($this->getDSN());
                }


                $this->link->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                $this->link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            catch (PDOException $pdoException) {
                throw new DatabaseException($pdoException->getMessage());
            }
        }
    }

    /**
     * Run a query against connected PDO link
     *
     * @param string $query
     * @param array $parameters
     *
     * @return boolean
     */
    public function query($query, $parameters=array()) {
        try {
            $statement = $this->link->prepare($query);
            $statement->execute($parameters);
            $this->results = $statement->fetchAll();
            $lower = strtolower($query);
            if (Utils::startsWith($lower, "update") || Utils::startsWith($lower, "delete")
                || Utils::startsWith($lower, "insert")) {
                $this->row_count = $statement->rowCount();
            }
            else {
                $this->row_count = count($this->results);
            }
            $this->query_count++;
            $this->all_queries[] = array($query, $parameters);
        }
        catch (PDOException $pdoException) {
            if ($this->transaction) {
                $this->link->rollBack();
                $this->transaction = false;
            }
            throw new DatabaseException($pdoException->getMessage(), $query, $parameters);
        }

        return true;
    }

    public function queryIterator($query, $parameters, $callback) {
        try {
            $statement = $this->link->prepare($query, [\PDO::ATTR_CURSOR => \PDO::CURSOR_SCROLL]);
            $statement->execute($parameters);
            $lower = strtolower($query);
            $this->query_count++;
            $this->all_queries[] = array($query, $parameters);
            if (Utils::startsWith($lower, "update") || Utils::startsWith($lower, "delete")
                || Utils::startsWith($lower, "insert")) {
                $this->row_count = $statement->rowCount();
                return null;
            }
            else {
                $this->row_count = null;
                return new DatabaseRowIterator($statement, $callback);
            }
        }
        catch (PDOException $exception) {
            throw new DatabaseException($exception->getMessage(), $query, $parameters);
        }
    }

    /**
     * Start a DB transaction, turning off autocommit mode. Queries won't be
     * actually commited to the database till Database::commit() is called.
     */
    public function beginTransaction() {
        if (!$this->transaction) {
            $this->transaction = $this->link->beginTransaction();
        }
    }

    /**
     * Actually commit/execute all queries to the database since we began the transaction.
     *
     * @throws \Exception
     */
    public function commit() {
        if ($this->transaction) {
            $this->link->commit();
            $this->transaction = false;
        }
    }

    /**
     * Returns a single row from the result set of the last ran query, removing the that row from
     * the result set.
     *
     * @return array
     */
    public function row() {
        if($this->results != NULL && count($this->results) > 0) {
            return array_shift($this->results);
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
    public function rows() {
        if($this->results !== null && count($this->results) > 0) {
            return $this->results;
        }
        else {
            return array();
        }
    }

    /**
     * If the last query was a SELECT, returns the number of rows returned else if
     * it's a UPDATE, DELETE, or INSERT, we use PDOStatement::rowCount to get the
     * number of affected rows.
     *
     * @link http://php.net/manual/en/pdostatement.rowcount.php
     *
     * @return int
     */
    public function rowCount() {
        return $this->row_count;
    }

    /**
     * Return count of total queries run against current PDO connection
     *
     * @return int
     */
    public function totalQueries() {
        return $this->query_count;
    }

    /**
     * Get all queries run against the current PDO connection
     *
     * @return string
     */
    public function getQueries() {
        $c = 1;
        $print = "";
        foreach($this->all_queries as $query) {
            $print .= ($c++).") ";
            foreach($query[1] as $parameter) {
                $query[0] = preg_replace('/\?/', "'{$parameter}'", $query[0], 1);
            }
            $print .= "{$query[0]}<br />";
        }
        return $print;
    }

    /**
     * "Disconnect" from current PDO connection by just setting
     * the link to null.
     */
    public function disconnect() {
        if ($this->transaction) {
            $this->commit();
        }
        $this->link = null;
    }

    /**
     * Returns true if we're connected to a database,
     * else return false
     *
     * @return bool
     */
    public function hasConnection() {
        return $this->link !== null;
    }

    public function inTransaction() {
        return $this->transaction;
    }

    public function getLastInsertId($name = "") {
        if (!empty($name)) {
            return $this->link->lastInsertId($name);
        }
        else {
            return $this->link->lastInsertId();
        }
    }
}
