<?php

namespace app\libraries\plagiarism;

class Interval {
    /** @var int */
    private $start;
    /** @var int */
    private $end;
    /** @var string */
    private $type;
    /** @var Submission[] */
    private $user_matches = [];

    public function __construct(int $start, int $end, string $type) {
        $this->start = $start;
        $this->end = $end;
        $this->type = $type;
    }

    public function getEnd(): int {
        return $this->end;
    }

    public function getStart(): int {
        return $this->start;
    }

    public function getType(): string {
        return $this->type;
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
