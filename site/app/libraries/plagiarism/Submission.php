<?php

namespace app\libraries\plagiarism;

class Submission {
    /** @var string */
    private $user_id;
    /** @var int */
    private $version;
    /** @var array */
    private $matching_positions;
    /** @var int */
    private $original_start_match;
    /** @var int */
    private $original_end_match;

    public function __construct(
        string $user_id,
        int $version,
        array $positions,
        int $original_start,
        int $original_end
    ) {
        $this->user_id = $user_id;
        $this->version = $version;
        $this->matching_positions = $positions;
        $this->original_start_match = $original_start;
        $this->original_end_match = $original_end;
    }

    public function getUserId(): string {
        return $this->user_id;
    }

    public function getVersion(): int {
        return $this->version;
    }

    public function getMatchingPositions(): array {
        return $this->matching_positions;
    }

    public function mergeMatchingPositions(array $positions): void {
        $this->matching_positions = array_merge($this->matching_positions, $positions);
    }

    public function getOriginalStartMatch(): int {
        return $this->original_start_match;
    }

    public function getOriginalEndMatch(): int {
        return $this->original_end_match;
    }
}
