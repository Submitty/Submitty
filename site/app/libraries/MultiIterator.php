<?php

namespace app\libraries;

use app\exceptions\NotImplementedException;

/**
 * Class MultiIterator
 * @package app\libraries
 *
 * An iterator that allows for sequentially iterating over each passed
 * iterator in-order, one at a time. This means that iterator2 will not be
 * called until iterator1 has been exhausted. This differs from
 * MultipleIterator as that commits all iterator functions over all attached
 * iterators in parallel (i.e. calling next() will return an array with the
 * results of next() on all attached iterators).
 *
 * @see \MultipleIterator
 */
class MultiIterator implements \Iterator {

    /** @var \Iterator[] */
    private $iterators = [];
    /** @var int */
    private $iterator_key = 0;
    /** @var \Iterator */
    private $curr_it = null;
    /** @var int  */
    private $key = 0;

    /**
     * MultiIterator constructor.
     * @param \Iterator[] $iterator_generators
     */
    public function __construct(...$iterator_generators) {
        $this->iterators = $iterator_generators;
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
            $this->iterator_key++;
            if (count($this->iterators) === 0 || count($this->iterators) === $this->iterator_key) {
                $this->curr_it = null;
                return;
            }
            $this->curr_it = $this->iterators[$this->iterator_key];
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
        // Seek after 'next' to be sure 'curr_it' is valid
        $this->seek();
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
     * Rewind each iterator that we've used as specified by the
     * the iterator key
     */
    public function rewind() {
        if (count($this->iterators) > $this->iterator_key) {
            $this->iterators[$this->iterator_key]->rewind();
        }

        while ($this->iterator_key > 0) {
            $this->iterator_key--;
            $this->iterators[$this->iterator_key]->rewind();
        }

        $this->key = 0;
        $this->iterator_key = 0;
        $this->curr_it = $this->iterators[$this->iterator_key];
    }
}
