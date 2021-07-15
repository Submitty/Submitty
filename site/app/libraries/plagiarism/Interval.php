<?php

namespace app\libraries\plagiarism;

class Interval {
    /** @var int */
    private $start;
    /** @var int */
    private $end;
    /** @var string */
    private $type;
    /** @var array */
    private $others = [];

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


    /**
     * @param string $user_id
     * @param int $version
     * @param int $start_pos
     * @param int $end_pos
     */
    public function addOther(string $user_id, int $version, string $source_gradeable, int $start_pos, int $end_pos): void {
        $pair = [];
        $pair["start"] = $start_pos;
        $pair["end"] = $end_pos;

        // add user+version pair if it doesn't already exist
        if (!isset($this->others["{$user_id}__{$version}__{$source_gradeable}"])) {
            $this->others["{$user_id}__{$version}__{$source_gradeable}"] = [];
        }

        // add the matching position
        $this->others["{$user_id}__{$version}__{$source_gradeable}"]["matchingpositions"][] = $pair;
    }

    public function updateStart(int $new_start): void {
        $this->start = $new_start;
    }

    public function updateEnd(int $new_end): void {
        $this->end = $new_end;
    }

    public function updateType(string $type): void {
        $this->type = $type;
    }

    /**
     * @param string $user_id
     * @param int $version
     * @param int $endIncrement
     */
    public function updateOthersEndPositions(string $user_id, int $version, string $source_gradeable, int $endIncrement): void {
        foreach ($this->others["{$user_id}__{$version}__{$source_gradeable}"]["matchingpositions"] as $i) {
            $i += $endIncrement;
        }
    }

    /**
     * @param string $user_id
     * @param int $version
     * @return array
     */
    public function getMatchingPositions(string $user_id, int $version, string $source_gradeable): array {
        return $this->others["{$user_id}__{$version}__{$source_gradeable}"]["matchingpositions"];
    }

    /**
     * @return array
     */
    public function getOthers(): array {
        return $this->others;
    }
}
