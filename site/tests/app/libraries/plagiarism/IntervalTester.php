<?php

declare(strict_types=1);

namespace tests\app\libraries\plagiarism;

use app\libraries\plagiarism\Interval;

class IntervalTester extends \PHPUnit\Framework\TestCase {
    public function testInterval() {
        $interval = new Interval(1, 2, "match");
        $this->assertSame(1, $interval->getStart());
        $this->assertSame(2, $interval->getEnd());
        $this->assertEmpty($interval->getOthers());
        $interval->updateStart(3);
        $interval->updateEnd(5);
        $this->assertSame(3, $interval->getStart());
        $this->assertSame(5, $interval->getEnd());

        $this->assertSame("match", $interval->getType());
        $interval->updateType("specific-match");
        $this->assertSame("specific-match", $interval->getType());
    }
}
