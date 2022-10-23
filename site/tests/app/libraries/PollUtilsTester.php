<?php

namespace tests\app\libraries;

use app\libraries\Core;
use app\libraries\PollUtils;
use app\models\PollModel;

class PollUtilsTester extends \tests\BaseUnitTest {
    use \phpmock\phpunit\PHPMock;

    public function setUp(): void {
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
            new PollModel(
                $this->createMockCore([],[],[
                    "getResponses" => ["Yes", "No", "Maybe"],
                    "getAnswers" => [0, 2],
                    "getUserResponses" => ["bitdiddle" => 0, "aphacker" => 1]
                ]),
                0,
                "Poll #1",
                "Is this the first poll?",
                "single-response",
                "closed",
                "2020-01-11",
                null,
                "never"
            ),
            new PollModel(
                $this->createMockCore([],[],[
                    "getResponses" => ["Yes", "No", "Definitely not"],
                    "getAnswers" => [0],
                    "getUserResponses" => ["bitdiddle" => 2, "aphacker" => 0]
                ]),
                1,
                "Poll #2",
                "Is this the second poll?",
                "single-response",
                "open",
                "2020-01-12",
                null,
                "always"
            ),
            new PollModel(
                $this->createMockCore([],[],[
                    "getResponses" => ["Yes", "No", "Maybe"],
                    "getAnswers" => [1],
                    "getUserResponses" => ["bitdiddle" => 1, "aphacker" => 2]
                ]),
                2,
                "Poll #3",
                "Is this the fourth poll?",
                "multiple-response",
                "ended",
                "2020-01-13",
                null,
                "when_ended"
            ),
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
                "image_path" => null,
                "release_histogram" => "never"
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
                "image_path" => null,
                "release_histogram" => "always"
            ],
            [
                "id" => 2,
                "name" => "Poll #3",
                "question" => "Is this the fourth poll?",
                "question_type" => "multiple-response",
                "responses" => ["Yes", "No", "Maybe"],
                "correct_responses" => [1],
                "release_date" => "2020-01-13",
                "status" => "ended",
                "image_path" => null,
                "release_histogram" => "when_ended"
            ]
        ];
        $actual_data = PollUtils::getPollExportData($polls);
        $this->assertSame($actual_data, $expected_data);
    }
}
