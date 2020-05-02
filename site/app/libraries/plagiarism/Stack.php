<?php

namespace app\libraries\plagiarism;

/**
 * Taken from: https://stackoverflow.com/questions/20210324/php-stack-implementation
 */
class Stack {

    protected $stack;
    protected $limit;

    public function __construct($initial = array()) {
        // initialize the stack
        $this->stack = $initial;
    }

    public function push($item) {
        // trap for stack overflow
        array_unshift($this->stack, $item);
    }

    public function pop() {
        if (!$this->isEmpty()) {
            // pop item from the start of the array
            return array_shift($this->stack);
        }
    }

    public function top() {
        return current($this->stack);
    }

    public function isEmpty() {
        return empty($this->stack);
    }
}
