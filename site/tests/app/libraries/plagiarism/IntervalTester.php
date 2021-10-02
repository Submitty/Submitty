<?php

declare(strict_types=1);

namespace tests\app\libraries\plagiarism;

use app\libraries\plagiarism\Interval;

class IntervalTester extends \PHPUnit\Framework\TestCase {

    private $my_interval;

    public function setUp(): void {
        $this->my_interval = [
            new Interval(13, 19, "match"),
            new Interval(73, 84, "common"),
            new Interval(34, 42, "provided"),
        ];
    }

    public function testGetters() {
        $this->assertEquals($this->my_interval[0]->getStart(), 13);
        $this->assertEquals($this->my_interval[0]->getEnd(), 19);
        $this->assertEquals($this->my_interval[0]->getType(), "match");

        $this->assertEquals($this->my_interval[1]->getStart(), 73);
        $this->assertEquals($this->my_interval[1]->getEnd(), 84);
        $this->assertEquals($this->my_interval[1]->getType(), "common");

        $this->assertEquals($this->my_interval[2]->getStart(), 34);
        $this->assertEquals($this->my_interval[2]->getEnd(), 42);
        $this->assertEquals($this->my_interval[2]->getType(), "provided");
    }

    public function testSetters() {
        $this->my_interval[0]->updateStart(15);
        $this->assertEquals($this->my_interval[0]->getStart(), 15);

        $this->my_interval[0]->updateEnd(22);
        $this->assertEquals($this->my_interval[0]->getEnd(), 22);

        $this->my_interval[0]->updateType("specific-match");
        $this->assertEquals($this->my_interval[0]->getType(), "specific-match");
    }

    public function testOtherMatchingPositions() {
        // TODO: should the interval class still support input matching
        // regions with no start and end positions?
        $this->assertEquals($this->my_interval[0]->getOthers(), []);
        $this->my_interval[0]->addOther("aphacker", 2, "homework_1", 3, 9);
        $this->assertEquals($this->my_interval[0]->getOthers(), [
            "aphacker__2__homework_1" => [
                "matchingpositions" => [
                    ["start" => 3, "end" => 9]
                ]
            ]
        ]);
        $this->my_interval[0]->addOther("aphacker", 2, "homework_1", 6, 12);
        $this->assertEquals($this->my_interval[0]->getOthers(), [
            "aphacker__2__homework_1" => [
                "matchingpositions" => [
                    ["start" => 3, "end" => 9],
                    ["start" => 6, "end" => 12]
                ]
            ]
        ]);
        $this->my_interval[0]->addOther("bitdiddle", 4, "homework_1", 21, 27);
        $this->assertEquals($this->my_interval[0]->getOthers(), [
            "aphacker__2__homework_1" => [
                "matchingpositions" => [
                    ["start" => 3, "end" => 9],
                    ["start" => 6, "end" => 12]
                ]
            ],
            "bitdiddle__4__homework_1" => [
                "matchingpositions" => [
                    ["start" => 21, "end" => 27]
                ]
            ]
        ]);

        $this->assertEquals($this->my_interval[0]->getMatchingPositions("aphacker", 2, "homework_1"), [
            ["start" => 3, "end" => 9],
            ["start" => 6, "end" => 12]
        ]);
        $this->assertEquals($this->my_interval[0]->getMatchingPositions("bitdiddle", 4, "homework_1"), [
            ["start" => 21, "end" => 27]
        ]);
        $this->assertEquals($this->my_interval[0]->getMatchingPositions("bitdiddle", 1, "homework_1"), []);
    }
}
