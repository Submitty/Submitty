<?php

namespace app\libraries\database;

use Doctrine\DBAL\Result;

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
    private Result $overall_result;
    private $callback;
    private mixed $current_result;
    private $key = -1;
    private $valid = true;

    /**
     * DatabaseRowIterator constructor.
     *
     * @param Result           $result
     * @param null|callable    $callback
     */
    public function __construct(Result $result, $callback = null) {
        $this->overall_result = $result;
        $this->callback = $callback;
        $this->next();
    }

    public function current(): mixed {
        return $this->current_result;
    }

    public function next(): void {
        if (!$this->valid()) {
            return;
        }
        $this->key++;
        $this->current_result = $this->overall_result->fetchAssociative();
        if ($this->current_result === false) {
            $this->valid = false;
            return;
        }
        if ($this->callback !== null) {
            $this->current_result = call_user_func($this->callback, $this->current_result);
        }
    }

    public function key(): int {
        return $this->key;
    }

    public function valid(): bool {
        return $this->valid;
    }

    public function rewind(): void {
    }

    public function close() {
        $this->overall_result->free();
        $this->valid = false;
        $this->current_result = null;
    }
}
