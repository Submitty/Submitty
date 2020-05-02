<?php

namespace app\libraries\plagiarism;

class Interval {

    private $start = 0;
    private $end = 0;
    private $user_matches = [];

    public function __construct($s, $e) {
        $this->start = $s;
        $this->end = $e;
    }

    public function getEnd() {
        return $this->end;
    }

    public function getStart() {
        return $this->start;
    }

    public function addUser($sub) {
        foreach ($this->user_matches as $s) {
            if ($sub->getUid() == $s->getUid() && $sub->getVid() == $s->getVid()) {
                $s->mergeMatchingPositions($sub->getMatchingPositions());
                return;
            }
        }
        $this->user_matches[] = $sub;
    }

    public function updateStart($newS) {
        $this->start = $newS;
    }

    public function updateEnd($newE) {
        $this->end = $newE;
    }

    public function getUsers() {
        return $this->user_matches;
    }
}
