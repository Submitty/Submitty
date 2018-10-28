<?php

namespace app\libraries;

/**
 * Class MultiIterator
 * @package app\libraries
 *
 * Allows the chaining of multiple iterators in one iterator object
 */
class MultiIterator implements \Iterator {

    /** @var \Iterator[] */
    private $iterators = [];
    /** @var \Iterator */
    private $curr_it = null;
    /** @var int  */
    private $key = 0;

    /**
     * MultiIterator constructor.
     * @param \Iterator[] $iterators
     */
    public function __construct(array $iterators) {
        foreach ($iterators as $iterator) {
            if (!($iterator instanceof  \Iterator)) {
                throw new \InvalidArgumentException('Parameter must be an iterator');
            }
        }
        $this->iterators = $iterators;
        $this->curr_it = array_shift($this->iterators);
    }

    /**
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since 5.0.0
     */
    public function current() {
        if ($this->curr_it === null) {
            return null;
        }
        return $this->curr_it->current();
    }

    /**
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function next() {
        // If we aren't valid, try to get the next one
        while (!$this->valid()) {
            $this->curr_it = array_shift($this->iterators);
            if ($this->curr_it === null) {
                return;
            }
        }
        $this->key++;
        $this->curr_it->next();
    }

    /**
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key() {
        return $this->key;
    }

    /**
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid() {
        if ($this->curr_it === null) {
            return false;
        }
        return $this->curr_it->valid();
    }

    /**
     * NOTE: This is an unsupported function
     */
    public function rewind() {
    }
}