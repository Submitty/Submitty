<?php

namespace app\libraries\plagiarism;

class Interval {
    /** @var int */
    private $start;
    /** @var int */
    private $end;
    /** @var Submission[] */
    private $user_matches = [];

    public function __construct(int $start, int $end) {
        $this->start = $start;
        $this->end = $end;
    }

    public function getEnd(): int {
        return $this->end;
    }

    public function getStart(): int {
        return $this->start;
    }

    public function addUser(Submission $sub): void {
        foreach ($this->user_matches as $s) {
            if ($sub->getUserId() == $s->getUserId() && $sub->getVersion() == $s->getVersion()) {
                $s->mergeMatchingPositions($sub->getMatchingPositions());
                return;
            }
        }
        $this->user_matches[] = $sub;
    }

    public function updateStart(int $new_start): void {
        $this->start = $new_start;
    }

    public function updateEnd(int $new_end): void {
        $this->end = $new_end;
    }

    /**
     * @return Submission[]
     */
    public function getUsers(): array {
        return $this->user_matches;
    }
}
