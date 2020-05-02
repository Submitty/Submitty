<?php

namespace app\libraries\plagiarism;

class Submission {
    private $userId;
    private $versionId;
    private $originalStartMatch;
    private $originalEndMatch;
    private $matchingPositions;

    public function __construct($uId, $vId, $pos, $ogS, $ogE) {
        $this->userId = $uId;
        $this->versionId = $vId;
        $this->matchingPositions = $pos;
        $this->originalStartMatch = $ogS;
        $this->originalEndMatch = $ogE;
    }

    public function getUid() {
        return $this->userId;
    }

    public function getVid() {
        return $this->versionId;
    }

    public function getMatchingPositions() {
        return $this->matchingPositions;
    }

    public function mergeMatchingPositions($newPositions) {
        $this->matchingPositions = array_merge($this->matchingPositions, $newPositions);
    }
}
