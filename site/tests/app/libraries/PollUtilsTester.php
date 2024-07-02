<?php

namespace tests\app\libraries;

use app\entities\poll\Option;
use app\entities\poll\Poll;
use app\libraries\DateUtils;
use app\libraries\PollUtils;
use DateTime;
use DateInterval;
use ReflectionProperty;

class PollUtilsTester extends \PHPUnit\Framework\TestCase {
    use \phpmock\phpunit\PHPMock;

    public function setUp(): void {
        DateUtils::setTimezone(new \DateTimeZone("America/New_York"));
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
                new DateInterval("PT1H"),
                new DateTime("2020-01-11"),
                "never",
                "never",
                "/var/local/submitty/courses/s21/sample/uploads/polls/poll_image_3_colors.png"
            ),
            new Poll(
                "Poll #2",
                "Is this the second poll?",
                "single-response",
                new DateInterval("PT1M"),
                new DateTime("2020-01-12"),
                "always",
                "always"
            ),
            new Poll(
                "Poll #3",
                "Is this the fourth poll?",
                "multiple-response",
                new DateInterval("PT1M30S"),
                new DateTime("2020-01-13"),
                "when_ended",
                "when_ended"
            ),
            new Poll(
                "Poll #4",
                "Is this the fifth poll?",
                "single-response-survey",
                new DateInterval("PT1M"),
                new DateTime("2020-01-13"),
                "when_ended",
                "when_ended"
            ),
        ];
        $polls[1]->setEndTime(null);
        $polls[2]->setEnded();
        $polls[3]->setOpen();
        $polls[3]->setAllowsCustomOptions(true);

        $poll_property = new ReflectionProperty("app\\entities\\poll\\Poll", "id");
        $poll_property->setAccessible(true);

        $poll_property->setValue($polls[0], 0);
        $poll_property->setValue($polls[1], 1);
        $poll_property->setValue($polls[2], 2);
        $poll_property->setValue($polls[3], 3);

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

        $option9 = new Option(0, "Yes", true);
        $option10 = new Option(1, "100%", true);
        $option11 = new Option(2, "Undoubtedly", true);
        $option12 = new Option(3, "Not sure", true, "aphacker");
        $option_property->setValue($option9, 9);
        $option_property->setValue($option10, 10);
        $option_property->setValue($option11, 11);
        $option_property->setValue($option12, 12);
        $polls[3]->addOption($option9);
        $polls[3]->addOption($option10);
        $polls[3]->addOption($option11);
        $polls[3]->addOption($option12);

        $expected_data = [
            [
                "id" => 0,
                "name" => "Poll #1",
                "question" => "Is this the first poll?",
                "question_type" => "single-response",
                "responses" => ["Yes", "No", "Maybe"],
                "correct_responses" => [0, 2],
                "duration" => "P0Y0M0DT1H0M0S",
                "end_time" => null,
                "release_date" => "2020-01-11",
                "release_histogram" => "never",
                "release_answer" => "never",
                "image_path" => "/var/local/submitty/courses/s21/sample/uploads/polls/poll_image_3_colors.png",
                "allows_custom" => false
            ],
            [
                "id" => 1,
                "name" => "Poll #2",
                "question" => "Is this the second poll?",
                "question_type" => "single-response",
                "responses" => ["Yes", "No", "Definitely Not"],
                "correct_responses" => [0],
                "duration" => "P0Y0M0DT0H1M0S",
                "end_time" => null,
                "release_date" => "2020-01-12",
                "release_histogram" => "always",
                "release_answer" => "always",
                "image_path" => null,
                "allows_custom" => false
            ],
            [
                "id" => 2,
                "name" => "Poll #3",
                "question" => "Is this the fourth poll?",
                "question_type" => "multiple-response",
                "responses" => ["Yes", "No", "Maybe"],
                "correct_responses" => [1],
                "duration" => "P0Y0M0DT0H1M30S",
                "end_time" => DateUtils::getDateTimeNow()->format("Y-m-d"),
                "release_date" => "2020-01-13",
                "release_histogram" => "when_ended",
                "release_answer" => "when_ended",
                "image_path" => null,
                "allows_custom" => false
            ],
            [
                "id" => 3,
                "name" => "Poll #4",
                "question" => "Is this the fifth poll?",
                "question_type" => "single-response-survey",
                "responses" => ["Yes", "100%", "Undoubtedly", "Not sure"],
                "correct_responses" => [0, 1, 2, 3],
                "duration" => "P0Y0M0DT0H1M0S",
                "end_time" => null,
                "release_date" => "2020-01-13",
                "release_histogram" => "when_ended",
                "release_answer" => "when_ended",
                "image_path" => null,
                "allows_custom" => true
            ]
        ];
        $actual_data = PollUtils::getPollExportData($polls);
        $this->assertSame($expected_data, $actual_data);
    }
}
