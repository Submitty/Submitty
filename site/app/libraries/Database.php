<?php

namespace app\libraries;

use \PDO;
use \PDOException;
use app\exceptions\DatabaseException;

/**
 * Class Database
 */
class Database {

    /**
     * @var PDO
     */
    private $link = null;

    /**
     * @var array
     */
    private $results = array();

    private $row_count = 0;

    /**
     * @var int
     */
    private $query_count = 0;

    /**
     * @var array
     */
    private $all_queries = array();

    /**
     * @var bool
     */
    private $transaction = false;

    private $host = null;
    private $user = null;
    private $password = null;
    private $name = null;
    private $type = null;

    /**
     * Database constructor.
     *
     * @param string $host
     * @param string $user
     * @param string $password
     * @param string $name
     * @param string $type
     */
    public function __construct($host, $user, $password, $name, $type='pgsql') {
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->name = $name;
        $this->type = $type;
    }

    /**
     * Connect to a database via PDO
     *
     * @throws DatabaseException
     */
    public function connect() {
        // Only start a new connection if we're not already connected to a DB
        if ($this->link == null) {
            $this->query_count = 0;
            $this->all_queries = array();

            try {
                $this->link = new PDO("{$this->type}:host={$this->host};dbname={$this->name}", $this->user, $this->password);

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
        if($this->results != NULL && count($this->results) > 0) {
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
            Database::commit();
        }
        $this->link = null;
        $this->query_count = 0;
        $this->all_queries = array();
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
