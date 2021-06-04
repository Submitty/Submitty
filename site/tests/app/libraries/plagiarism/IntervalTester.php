<?php

declare(strict_types=1);

namespace tests\app\libraries\plagiarism;

use app\libraries\plagiarism\Interval;
use app\libraries\plagiarism\Submission;

class IntervalTester extends \PHPUnit\Framework\TestCase {
    public function testInterval() {
        $interval = new Interval(1, 2, "match");
        $this->assertSame(1, $interval->getStart());
        $this->assertSame(2, $interval->getEnd());
        $this->assertEmpty($interval->getUsers());
        $interval->updateStart(3);
        $interval->updateEnd(5);
        $this->assertSame(3, $interval->getStart());
        $this->assertSame(5, $interval->getEnd());
        /** @var Submission[] */
        $users = [];
        $users[] = new Submission('a', 1, [['start' => 3, 'end' => 4]], 3, 5);
        $interval->addUser($users[0]);
        $this->assertSame($users, $interval->getUsers());
        $users[] = new Submission('b', 1, [['start' => 2, 'end' => 3]], 5, 6);
        $interval->addUser($users[1]);
        $this->assertSame($users, $interval->getUsers());
        $users[] = new Submission('a', 2, [['start' => 1, 'end' => 2]], 10, 11);
        $interval->addUser($users[2]);
        $this->assertSame($users, $interval->getUsers());
        $users[0]->mergeMatchingPositions([['start' => 2, 'end' => 3]]);
        $interval->addUser(new Submission('a', 1, [['start' => 2, 'end' => 3]], 20, 30));
        $this->assertSame($users, $interval->getUsers());
    }
}
