<?php

declare(strict_types=1);

namespace tests\app\libraries\plagiarism;

use app\libraries\plagiarism\Submission;

class SubmissionTester extends \PHPUnit\Framework\TestCase {
    public function testSubmission() {
        $submission = new Submission('person', 1, [['start' => 5, 'end' => 10]], 3, 5);
        $this->assertSame('person', $submission->getUserId());
        $this->assertSame(1, $submission->getVersion());
        $this->assertSame([['start' => 5, 'end' => 10]], $submission->getMatchingPositions());
        $this->assertSame(3, $submission->getOriginalStartMatch());
        $this->assertSame(5, $submission->getOriginalEndMatch());
        $submission->mergeMatchingPositions([['start' => 11, 'end' => 30]]);
        $this->assertSame(
            [['start' => 5, 'end' => 10], ['start' => 11, 'end' => 30]],
            $submission->getMatchingPositions()
        );
    }
}
