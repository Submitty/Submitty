<?php

namespace app\libraries\database;


class DatabaseRowIterator implements \Iterator {
    private $statement;
    private $callback;
    private $result;
    private $key = 0;
    private $valid = true;

    /**
     * DatabaseRowIterator constructor.
     *
     * @param \PDOStatement $statement
     * @param null|callable $callback
     */
    public function __construct(\PDOStatement $statement, $callback=null) {
        $this->statement = $statement;
        $this->callback = $callback;
        $this->next();
    }

    public function current() {
        return $this->result;
    }

    public function next() {
        // The row offset starts at 1 (not 0)
        $this->result = $this->statement->fetch(\PDO::FETCH_ASSOC, \PDO::FETCH_ORI_ABS, ++$this->key);
        if ($this->result === false) {
            $this->valid = false;
            return null;
        }
        if ($this->callback !== null) {
            $this->result = call_user_func($this->callback, $this->result);
        }
        return $this->result;
    }

    public function key() {
        return $this->key;
    }

    public function valid() {
        return $this->valid;
    }

    public function rewind() {
        $this->key = 0;
        $this->next();
    }
}
