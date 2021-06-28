<?php

namespace tests\app\libraries;

use app\libraries\Core;
use app\libraries\PollUtils;
use app\models\PollModel;

class PollUtilsTester extends \PHPUnit\Framework\TestCase {
    use \phpmock\phpunit\PHPMock;

    private $core;

    public function setUp(): void {
        $this->core = new Core();
    }

    public function tearDown(): void {
    }

    public function testExportDataWithEmptyPolls() {
        $polls = [];
        $expected_data = [];
        $actual_data = PollUtils::getPollExportData($polls);
        $this->assertSame($actual_data, $expected_data);
    }

    public function testExportDataWithNonEmptyPolls() {
        $polls = [
            new PollModel($this->core, 0, "Poll #1", "Is this the first poll?", "single-response", ["Yes", "No", "Maybe"], [0, 2], "closed", ["bitdiddle" => 0, "aphacker" => 1], "2020-01-11", null),
            new PollModel($this->core, 1, "Poll #2", "Is this the second poll?", "single-response", ["Yes", "No", "Definitely not"], [0], "open", ["bitdiddle" => 2, "aphacker" => 0], "2020-01-12", null),
            new PollModel($this->core, 2, "Poll #3", "Is this the fourth poll?", "multilple-response", ["Yes", "No", "Maybe"], [1], "ended", ["bitdiddle" => 1, "aphacker" => 2], "2020-01-13", null),
        ];
        $expected_data = [
            [
                "id" => 0,
                "name" => "Poll #1",
                "question" => "Is this the first poll?",
                "question_type" => "single-response",
                "responses" => ["Yes", "No", "Maybe"],
                "correct_responses" => [0, 2],
                "release_date" => "2020-01-11",
                "status" => "closed",
                "image_path" => null
            ],
            [
                "id" => 1,
                "name" => "Poll #2",
                "question" => "Is this the second poll?",
                "question_type" => "single-response",
                "responses" => ["Yes", "No", "Definitely not"],
                "correct_responses" => [0],
                "release_date" => "2020-01-12",
                "status" => "open",
                "image_path" => null
            ],
            [
                "id" => 2,
                "name" => "Poll #3",
                "question" => "Is this the fourth poll?",
                "question_type" => "multilple-response",
                "responses" => ["Yes", "No", "Maybe"],
                "correct_responses" => [1],
                "release_date" => "2020-01-13",
                "status" => "ended",
                "image_path" => null
            ]
        ];
        $actual_data = PollUtils::getPollExportData($polls);
        $this->assertSame($actual_data, $expected_data);
    }
}
