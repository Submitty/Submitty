<?php

namespace tests\app\libraries;

use app\entities\poll\Option;
use app\entities\poll\Poll;
use app\libraries\PollUtils;
use DateTime;
use ReflectionProperty;

class PollUtilsTester extends \PHPUnit\Framework\TestCase {
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
            new Poll(
                "Poll #1",
                "Is this the first poll?",
                "single-response",
                new DateTime("2020-01-11"),
                "never",
                "/var/local/submitty/courses/s21/sample/uploads/polls/poll_image_3_colors.png"
            ),
            new Poll(
                "Poll #2",
                "Is this the second poll?",
                "single-response",
                new DateTime("2020-01-12"),
                "always"
            ),
            new Poll(
                "Poll #3",
                "Is this the fourth poll?",
                "multiple-response",
                new DateTime("2020-01-13"),
                "when_ended"
            ),
        ];

        $polls[1]->setOpen();
        $polls[2]->setEnded();

        $poll_property = new ReflectionProperty("app\\entities\\poll\\Poll", "id");
        $poll_property->setAccessible(true);

        $poll_property->setValue($polls[0], 0);
        $poll_property->setValue($polls[1], 1);
        $poll_property->setValue($polls[2], 2);

        $option_property = new ReflectionProperty("app\\entities\\poll\\Option", "id");
        $option_property->setAccessible(true);

        $option0 = new Option(0, "Yes", true);
        $option1 = new Option(1, "No", false);
        $option2 = new Option(2, "Maybe", true);
        $option_property->setValue($option0, 0);
        $option_property->setValue($option1, 1);
        $option_property->setValue($option2, 2);
        $polls[0]->addOption($option0);
        $polls[0]->addOption($option1);
        $polls[0]->addOption($option2);

        $option3 = new Option(0, "Yes", true);
        $option4 = new Option(1, "No", false);
        $option5 = new Option(2, "Definitely Not", false);
        $option_property->setValue($option3, 3);
        $option_property->setValue($option4, 4);
        $option_property->setValue($option5, 5);
        $polls[1]->addOption($option3);
        $polls[1]->addOption($option4);
        $polls[1]->addOption($option5);

        $option6 = new Option(0, "Yes", false);
        $option7 = new Option(1, "No", true);
        $option8 = new Option(2, "Maybe", false);
        $option_property->setValue($option6, 6);
        $option_property->setValue($option7, 7);
        $option_property->setValue($option8, 8);
        $polls[2]->addOption($option6);
        $polls[2]->addOption($option7);
        $polls[2]->addOption($option8);

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
                "image_path" => "/var/local/submitty/courses/s21/sample/uploads/polls/poll_image_3_colors.png",
                "release_histogram" => "never"
            ],
            [
                "id" => 1,
                "name" => "Poll #2",
                "question" => "Is this the second poll?",
                "question_type" => "single-response",
                "responses" => ["Yes", "No", "Definitely Not"],
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
        $this->assertSame($expected_data, $actual_data);
    }
}
