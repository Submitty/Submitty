<?php

namespace app\libraries\database;

use app\exceptions\DatabaseException;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Logging\Middleware;

/**
 * @psalm-import-type Params from DriverManager
 */
abstract class AbstractDatabase {
    /**
     * @var Connection|null
     */
    protected $conn = null;

    /**
     * @var array
     */
    protected $results = [];

    protected $row_count = 0;

    protected ?QueryLogger $query_logger = null;

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

    /**
     * @psalm-return Params
     */
    abstract public function getConnectionDetails(): array;

    public function getConnection(): Connection {
        if ($this->conn === null) {
            throw new DatabaseException("Database not yet connected");
        }
        return $this->conn;
    }

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
    abstract public function fromDatabaseToPHPArray($text, $parse_bools = false, $start = 0, &$end = null);
    abstract public function fromPHPToDatabaseArray($array);

    /**
     * Connects to a database through the DBAL library.
     * We wrap the potential exception that would get thrown by the DBAL constructor so that we can
     * bubble up the message, without exposing any of the parameters used by the connect function
     * as we don't wany anyone to get the DB details.
     *
     * @throws DatabaseException
     */
    public function connect(bool $debug = false) {
        // Only start a new connection if we're not already connected to a DB
        if ($this->conn === null) {
            try {
                $config = new Configuration();
                if ($debug) {
                    $this->query_logger = new QueryLogger();
                    $logger_middleware = new Middleware($this->query_logger);
                    $config->setMiddlewares([$logger_middleware]);
                }
                $details = $this->getConnectionDetails();
                $this->conn = DriverManager::getConnection($details, $config);
            }
            catch (DBALException $dbalException) {
                throw new DatabaseException($dbalException->getMessage());
            }
        }
    }

    /**
     * "Disconnect" from the underlying connection and set it to null for any further new connections.
     * This will additionally rollback any open transactions before disconnecting.
     */
    public function disconnect() {
        if ($this->transaction) {
            $this->rollback();
        }
        if ($this->conn !== null) {
            $this->conn->close();
        }
        $this->conn = null;
    }

    /**
     * @return bool Returns true if we're connected to a database, else return false
     */
    public function isConnected() {
        return $this->conn !== null;
    }

    /**
     * Run a query against the connected DBAL DB.
     * This function will throw an exception if it fails.
     * Otherwise, it can be assumed it succeeded.
     *
     * @param string $query
     * @param array $parameters
     */
    public function query($query, $parameters = []): void {
        try {
            foreach ($parameters as &$parameter) {
                if (gettype($parameter) === "boolean") {
                    $parameter = $this->convertBoolean($parameter);
                }
                elseif (gettype($parameter) === "object") {
                    if (get_class($parameter) === "DateTime") {
                        $parameter = $parameter->format("Y-m-d H:i:sO");
                    }
                }
            }
            $statement = $this->conn->prepare($query);
            $result = $statement->executeQuery($parameters);

            $this->row_count = null;
            $identity = QueryIdentifier::identify($query);
            if (
                in_array($identity, [QueryIdentifier::UPDATE, QueryIdentifier::DELETE, QueryIdentifier::INSERT])
            ) {
                $this->row_count = $result->rowCount();
            }
            elseif ($identity === QueryIdentifier::SELECT) {
                $this->results = $result->fetchAllAssociative();
                $this->row_count = count($this->results);
            }
        }
        catch (DBALException $dbalException) {
            throw new DatabaseException($dbalException->getMessage(), $query, $parameters);
        }
    }

    /**
     * Given a query, if it's a SELECT, it'll run the query against the DB returning a {@see DatabaseIterator}
     * which can be used to scroll through the results. However, if the query is of any other type, it'll
     * run it through the query() function which will just return a boolean on if the function succeeded or
     * not. In all cases, it will throw a {@see DatabaseException} on an invalid query.
     *
     * @param string $query
     * @param array $parameters
     * @param callable|null $callback
     *
     * @return DatabaseRowIterator|bool
     *
     * @throws \app\exceptions\DatabaseException
     */
    public function queryIterator(string $query, array $parameters = [], $callback = null) {
        $lower = trim(strtolower($query));
        if (!str_starts_with($lower, "select")) {
            $this->query($query, $parameters);
            return true;
        }
        try {
            $statement = $this->conn->prepare($query);
            $result = $statement->executeQuery($parameters);
            $this->row_count = null;
            return new DatabaseRowIterator($result, $callback);
        }
        catch (DBALException $exception) {
            throw new DatabaseException($exception->getMessage(), $query, $parameters);
        }
    }

    /**
     * Start a DB transaction, turning off autocommit mode. Queries won't be
     * actually committed to the database till Database::commit() is called.
     */
    public function beginTransaction(): void {
        if (!$this->transaction) {
            $this->transaction = $this->conn->beginTransaction();
        }
    }

    /**
     * Actually commit/execute all queries to the database since we began the transaction.
     */
    public function commit(): void {
        if ($this->transaction) {
            $this->conn->commit();
            $this->transaction = false;
        }
    }

    public function rollback(): void {
        if ($this->transaction) {
            $this->conn->rollBack();
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
        if ($this->results != null && count($this->results) > 0) {
            return array_shift($this->results);
        }
        else {
            return [];
        }
    }

    /**
     * Get all the rows (minus any gotten via Database::row() before calling
     * this function) from the last query
     *
     * @return array
     */
    public function rows(): array {
        if ($this->results !== null && count($this->results) > 0) {
            return $this->results;
        }
        else {
            return [];
        }
    }

    /**
     * If the last query was a SELECT, returns the number of rows returned else if
     * it's a UPDATE, DELETE, or INSERT, we use Result::rowCount to get the
     * number of affected rows.
     *
     * @link http://php.net/manual/en/pdostatement.rowcount.php
     *
     * @return int
     */
    public function getRowCount(): int {
        return $this->row_count;
    }

    /**
     * Return count of total queries run against current DBAL connection
     */
    public function getQueryCount(): int {
        if ($this->query_logger === null) {
            return 0;
        }
        return count($this->query_logger->getQueries());
    }

    public function getQueries(): array {
        if ($this->query_logger === null) {
            return [];
        }
        return $this->query_logger->getQueries();
    }

    /**
     * Get all queries run against the current DBAL connection, with placeholders replaced by their values
     *
     * @return string[]
     */
    public function getPrintQueries(): array {
        $print = [];
        foreach ($this->query_logger->getQueries() as $query) {
            $print[] = DatabaseUtils::formatQuery($query[0], $query[1]);
        }
        return $print;
    }

    public function hasDuplicateQueries(): bool {
        $queries = [];
        foreach ($this->query_logger->getQueries() as $query) {
            $queries[] = $query[0];
        }
        return count($queries) !== count(array_unique($queries));
    }

    /**
     * @return bool
     */
    public function inTransaction(): bool {
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
        return $this->conn->lastInsertId($name);
    }

    /**
     * This converts a Boolean to our DB representation and for how we represent bool. For DBs that don't support
     * booleans, we generally use a tinyint(1) and just use 0/1 to represent it else we convert it to whatever
     * works for that given database.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function convertBoolean($value) {
        return ($value === true) ? 1 : 0;
    }
}
