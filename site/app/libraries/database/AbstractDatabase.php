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
     * Should we emulate prepares within PDO. Generally we want to leave this to false, but for some
     * drivers (such as PDO_MySQL, it may be beneficial for performace to turn this to true
     * @var bool
     */
    protected $emulate_prepares = false;

    protected $columns = array();

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

    abstract public function getDSN();

    /**
     * Given a string representation of an array from the database, convert it to a PHP
     * array.
     *
     * @param string $text        the text representation of the postgres array
     * @param bool   $parse_bools set to true to convert "true"/"false" to booleans instead of strings
     * @param int    $start       index to start looking through $text at
     * @param int    $end         index of $text where we exist current pgArrayToPhp call
     *
     * @return array
     */
    abstract public function fromDatabaseToPHPArray($text, $parse_bools = false, $start=0, &$end=null);
    abstract public function fromPHPToDatabaseArray($array);

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

                $this->link->setAttribute(PDO::ATTR_EMULATE_PREPARES, $this->emulate_prepares);
                $this->link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            catch (PDOException $pdoException) {
                throw new DatabaseException($pdoException->getMessage());
            }
        }
    }

    /**
     * "Disconnect" from current PDO connection by just setting the link to null, and PDO will take care of actually
     * recycling the connection upon the GC destruction of the PDO object. This will additionally commit any open
     * transactions before disconnecting.
     */
    public function disconnect() {
        if ($this->transaction) {
            $this->rollback();
        }
        $this->link = null;
    }

    /**
     * @return bool Returns true if we're connected to a database, else return false
     */
    public function isConnected() {
        return $this->link !== null;
    }

    /**
     * Run a query against the connected PDO DB.
     *
     * @param string $query
     * @param array $parameters
     *
     * @return boolean true if query suceeded, else false.
     */
    public function query($query, $parameters=array()) {
        try {
            $this->query_count++;
            $this->all_queries[] = array($query, $parameters);
            $statement = $this->link->prepare($query);
            $result = $statement->execute($parameters);
            $lower = trim(strtolower($query));

            $this->row_count = null;
            if (Utils::startsWith($lower, 'update') || Utils::startsWith($lower, 'delete')
                || Utils::startsWith($lower, 'insert')) {
                $this->row_count = $statement->rowCount();
            }
            elseif (Utils::startsWith($lower, 'select')) {
                $columns = $this->getColumnData($statement);
                $this->results = $statement->fetchAll(\PDO::FETCH_ASSOC);
                // Under normal circumstances, we don't really need to worry about $this->results being false.
                // @codeCoverageIgnoreStart
                if ($this->results === false) {
                    return false;
                }
                // @codeCoverageIgnoreEnd
                foreach ($this->results as $idx => $result) {
                    $this->results[$idx] = $this->transformResult($result, $columns);
                }
                $this->row_count = count($this->results);
            }
        }
        catch (PDOException $pdoException) {
            throw new DatabaseException($pdoException->getMessage(), $query, $parameters);
        }

        return $result;
    }

    /**
     * Given a query, if it's a SELECT, it'll run the query against the DB returning a {@see DatabaseIterator}
     * which can be used to scroll through the results. However, if the query is of any other type, it'll
     * run it through the query() function which will just return a boolean on if the function suceeded or
     * not. In all cases, it will throw a {@see DatabaseException} on an invalid query.
     *
     * @param $query
     * @param $parameters
     * @param $callback
     *
     * @return DatabaseRowIterator|bool
     *
     * @throws \app\exceptions\DatabaseException
     */
    public function queryIterator($query, $parameters=array(), $callback=null) {
        $lower = trim(strtolower($query));
        if (!Utils::startsWith($lower, "select")) {
            return $this->query($query, $parameters);
        }
        try {
            $this->query_count++;
            $this->all_queries[] = array($query, $parameters);
            $statement = $this->link->prepare($query);
            $statement->execute($parameters);
            $this->row_count = null;
            return new DatabaseRowIterator($statement, $this, $callback);
        }
        catch (PDOException $exception) {
            throw new DatabaseException($exception->getMessage(), $query, $parameters);
        }
    }

    /**
     * @param \PDOStatement $statement
     *
     * @return array
     */
    public function getColumnData($statement) {
        $columns = array();
        for ($i = 0; $i < $statement->columnCount(); $i++) {
            $col = $statement->getColumnMeta($i);
            if ($col !== false) {
                $columns[$col['name']] = $col;
            }
        }
        return $columns;
    }

    /**
     * @param $result
     * @param $columns
     *
     * @return mixed
     */
    public function transformResult($result, $columns) {
        foreach ($result as $col => $value) {
            if (isset($columns[$col])) {
                $column = $columns[$col];
                if ($column['native_type'] === 'integer' && $column['pdo_type'] !== PDO::PARAM_INT) {
                    $value = (integer) $value;
                }
                elseif ($column['native_type'] === 'boolean' && $column['pdo_type'] !== PDO::PARAM_BOOL) {
                    $value = (boolean) $value;
                }
                $result[$col] = $value;
            }
        }
        return $result;
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
     */
    public function commit() {
        if ($this->transaction) {
            $this->link->commit();
            $this->transaction = false;
        }
    }

    public function rollback() {
        if ($this->transaction) {
            $this->link->rollBack();
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
    public function getRowCount() {
        return $this->row_count;
    }

    /**
     * Return count of total queries run against current PDO connection
     *
     * @return int
     */
    public function getQueryCount() {
        return count($this->all_queries);
    }

    public function getQueries() {
        return $this->all_queries;
    }

    /**
     * Get all queries run against the current PDO connection
     *
     * @return string
     */
    public function getPrintQueries() {
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
     * @return bool
     */
    public function inTransaction() {
        return $this->transaction;
    }

    /**
     * Get the 'ID' (generally the Primary Key) of the last inserted row, either from the last insert
     * (if $name is null) or pertaining to the series given by $name (if supported). Some drivers, like
     * PDO_PGSQL, require a $name perameter to return the ID while some, like PDO_SQLITE, largely ignore it.
     *
     * @param string $name name of the sequence to get the ID of (if supported)
     *
     * @return mixed ID of the last inserted row
     */
    public function getLastInsertId($name = null) {
        return $this->link->lastInsertId($name);
    }
}
