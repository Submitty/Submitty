<?php

namespace app\libraries;

/**
 * Class MultiIterator
 * @package app\libraries
 *
 * Allows the chaining of multiple iterators in one iterator object
 */
class MultiIterator implements \Iterator {

    /** @var array array of [\Closure, $this] */
    private $iterator_generators = [];
    /** @var \Iterator */
    private $curr_it = null;
    /** @var int  */
    private $key = 0;

    /**
     * MultiIterator constructor.
     * @param \Closure[] $iterator_generators
     */
    public function __construct(array $iterator_generators) {
        $this->iterator_generators = $iterator_generators;
        $this->seek();
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
     * Loads the next iterator with contents or keeps the current if still valid
     */
    private function seek() {
        // If we aren't valid, try to get the next one
        while (!$this->valid()) {
            $cl = array_shift($this->iterator_generators);
            if ($cl === null) {
                $this->curr_it = null;
                return;
            }
            $this->curr_it = $cl[0]->call($cl[1]);
        }
    }

    /**
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function next() {
        $this->seek();
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