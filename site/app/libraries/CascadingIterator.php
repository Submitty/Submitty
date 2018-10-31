<?php

namespace app\libraries;

/**
 * Class CascadingIterator
 * @package app\libraries
 *
 * An iterator that allows for sequentially iterating over each passed
 * iterator in-order, one at a time. This means that iterator2 will not be
 * called until iterator1 has been exhausted.
 */
class CascadingIterator implements \Iterator {

    /** @var \Iterator[] */
    private $iterators = [];
    /** @var int */
    private $iterator_key = 0;
    /** @var int  */
    private $key = 0;

    /**
     * MultiIterator constructor.
     * @param \Iterator[] $iterators
     */
    public function __construct(...$iterators) {
        $this->iterators = $iterators;
        $this->seek();
    }

    /**
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since 5.0.0
     */
    public function current() {
        if ($this->iterator_key >= count($this->iterators)) {
            return null;
        }
        return $this->iterators[$this->iterator_key]->current();
    }

    /**
     * Loads the next iterator with contents or keeps the current if still valid
     */
    private function seek() {
        // If we aren't valid, try to get the next one
        while (!$this->valid()) {
            $this->iterator_key++;
            if (count($this->iterators) === 0 || count($this->iterators) === $this->iterator_key) {
                return;
            }
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
        $this->iterators[$this->iterator_key]->next();
        // Seek after 'next' to be sure our current iterator is valid
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

    public function iteratorKey() {
        return $this->iterator_key;
    }

    /**
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid() {
        if ($this->iterator_key >= count($this->iterators)) {
            return false;
        }
        return $this->iterators[$this->iterator_key]->valid();
    }

    /**
     * Rewind each iterator that we've used as specified by the
     * the iterator key
     */
    public function rewind() {
        while ($this->iterator_key >= count($this->iterators)) {
            $this->iterator_key--;
        }

        while ($this->iterator_key >= 0) {
            $this->iterators[$this->iterator_key]->rewind();
            $this->iterator_key--;
        }

        $this->key = 0;
        $this->iterator_key = 0;
    }
}
