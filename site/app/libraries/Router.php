<?php

namespace app\libraries;

class Router {
    private $pieces = [];

    public function __construct(string $url) {
        if (!empty($url)) {
            $this->pieces = explode('/', $url);
        }
    }

    public function getNext(): ?string {
        return array_shift($this->pieces);
    }

    public function hasNext(): bool {
        return count($this->pieces) > 0;
    }
}
