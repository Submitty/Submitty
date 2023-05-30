<?php

namespace app\libraries\database;

/**
 * Class DatabaseRowIterator
 *
 * This class allows you to iterate through a ResultSet from the DB loading only one row at a time into memory,
 * which is useful for some of the larger queries of the system. Additionally, one can pass in a callback function
 * to be applied to each row returned by the Iterator before it's returned to the caller which is useful for
 * constructing models out of the results returned.
 *
 * See {@link http://php.net/manual/en/class.iterator.php} for an explanation of function calls when an Iterator is
 * used within a foreach loop.
 */
class DatabaseRowIterator implements \Iterator {
    private $statement;
    private $database;
    private $callback;
    private $result;
    private $key = -1;
    private $valid = true;
    private $columns = [];

    /**
     * DatabaseRowIterator constructor.
     *
     * @param \PDOStatement    $statement
     * @param AbstractDatabase $database
     * @param null|callable    $callback
     */
    public function __construct(\PDOStatement $statement, $database, $callback = null) {
        echo "_construct";
        $this->statement = $statement;
        $this->database = $database;
        $this->columns = $this->database->getColumnData($this->statement);
        $this->callback = $callback;
        echo "#how_many_repeatsssssss#";
        $this->next();
    }


    public function current() {
        return $this->result;
    }

    public function next() {
        echo "#next#";
        if (!$this->valid()) {
            echo "wasn't valid";
            return;
        }
        $this->key++;
        $this->result = $this->statement->fetch(\PDO::FETCH_ASSOC);
        // echo "fetch was false";
        if ($this->result === false) {
            echo "#fetch was false#";
            $this->valid = false;
            return;
        }

        $this->result = $this->database->transformResult($this->result, $this->columns);
        //print_r($this->result);
        if ($this->callback !== null) {
            /** @var mixed $this->result */
            echo "#about to call_user_func#";
            $this->result = call_user_func($this->callback, $this->result);
            echo "done";
        }
        return;
    }

    public function key() {
        return $this->key;
    }

    public function valid() {
        return $this->valid;
    }

    public function rewind() {
    }

    public function close() {
        $this->statement->closeCursor();
        $this->valid = false;
        $this->result = null;
    }
}
