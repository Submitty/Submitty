<?php

namespace app\libraries\plagiarism;

class Interval {
    /** @var int */
    private int $start;
    /** @var int */
    private int $end;
    /** @var string */
    private string $type;
    /** @var array */
    private array $others = [];

    /**
     * Interval constructor.
     * @param int $start
     * @param int $end
     * @param string $type
     */
    public function __construct(int $start, int $end, string $type) {
        $this->start = $start;
        $this->end = $end;
        $this->type = $type;
    }

    /**
     * @return int
     */
    public function getEnd(): int {
        return $this->end;
    }

    /**
     * @return int
     */
    public function getStart(): int {
        return $this->start;
    }

    /**
     * @return string
     */
    public function getType(): string {
        return $this->type;
    }


    /**
     * @param string $user_id
     * @param int $version
     * @param string $source_gradeable
     * @param int $start_pos
     * @param int $end_pos
     */
    public function addOther(string $user_id, int $version, string $source_gradeable, int $start_pos = -1, int $end_pos = -1): void {
        $pair = [];
        $pair["start"] = $start_pos;
        $pair["end"] = $end_pos;

        // add user+version pair if it doesn't already exist
        if (!isset($this->others["{$user_id}__{$version}__{$source_gradeable}"])) {
            $this->others["{$user_id}__{$version}__{$source_gradeable}"] = [];
        }

        // add the matching position
        if ($start_pos === -1 && $end_pos === -1) {
            $this->others["{$user_id}__{$version}__{$source_gradeable}"]["matchingpositions"] = [];
        }
        else {
            $this->others["{$user_id}__{$version}__{$source_gradeable}"]["matchingpositions"][] = $pair;
        }
    }

    /**
     * @param int $new_start
     */
    public function updateStart(int $new_start): void {
        $this->start = $new_start;
    }

    /**
     * @param int $new_end
     */
    public function updateEnd(int $new_end): void {
        $this->end = $new_end;
    }

    /**
     * @param string $type
     */
    public function updateType(string $type): void {
        $this->type = $type;
    }

    /**
     * @param string $user_id
     * @param int $version
     * @param string $source_gradeable
     * @return array
     */
    public function getMatchingPositions(string $user_id, int $version, string $source_gradeable): array {
        if (isset($this->others["{$user_id}__{$version}__{$source_gradeable}"])) {
            return $this->others["{$user_id}__{$version}__{$source_gradeable}"]["matchingpositions"];
        }
        else {
            return [];
        }
    }

    /**
     * @return array
     */
    public function getOthers(): array {
        return $this->others;
    }
}
