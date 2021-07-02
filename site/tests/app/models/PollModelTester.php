<?php

namespace tests\app\models;

use app\libraries\Core;
use app\models\PollModel;
use app\libraries\DateUtils;

class PollModelTester extends \PHPUnit\Framework\TestCase {

    private $core;
    private $my_polls;

    public function setUp(): void {
        $this->core = new Core();
        $this->my_polls = [
            0 => new PollModel(
                $this->core,                                       // core
                0,                                                 // id
                "Poll #1",                                         // name
                "Is this the first poll?",                         // question
                "single-response-single-correct",                  // question_type
                [0 => "Yes", 1 => "No", 2 => "Maybe"],             // responses
                [0 => 0],                                          // asnwers
                "closed",                                          // status
                ["bitdiddle" => [0 => 1], "aphacker" => [0 => 1]], // user_responses
                "2021-01-11",                                      // release_date
                null                                               // image path
            ),
            1 => new PollModel(
                $this->core,
                1,
                "Poll #2",
                "Is this the first poll?",
                "single-response-multiple-correct",
                [0 => "Absolutely", 1 => "No", 2 => "Perhaps"],
                [0 => 1, 1 => 2],
                "open",
                ["bitdiddle" => [0 => 2], "aphacker" => [0 => 0]],
                "9999-12-31",
                null
            ),
            2 => new PollModel(
                $this->core,
                2,
                "Poll #3",
                "What is your favorite color?",
                "multiple-response-survey",
                [0 => "Red", 1 => "Blue", 2 => "Yellow", 3 => "Green"],
                [0 => 0, 1 => 1, 2 => 2, 3 => 3],
                "ended",
                ["bitdiddle" => [0 => 0, 1 => 2, 2 => 3], "aphacker" => [0 => 1, 1 => 3]],
                date("Y-m-d"),
                "/var/local/submitty/courses/s21/sample/uploads/polls/poll_image_3_colors.png"
            )
        ];
    }

    public function tearDown(): void {
    }

    public function testId(): void {
        $this->assertEquals($this->my_polls[0]->getId(), 0);
        $this->assertEquals($this->my_polls[1]->getId(), 1);
        $this->assertEquals($this->my_polls[2]->getId(), 2);
    }

    public function testName(): void {
        $this->assertEquals($this->my_polls[0]->getName(), "Poll #1");
        $this->assertEquals($this->my_polls[1]->getName(), "Poll #2");
        $this->assertEquals($this->my_polls[2]->getName(), "Poll #3");
    }

    public function testQuestion(): void {
        $this->assertEquals($this->my_polls[0]->getQuestion(), "Is this the first poll?");
        $this->assertEquals($this->my_polls[1]->getQuestion(), "Is this the first poll?");
        $this->assertEquals($this->my_polls[2]->getQuestion(), "What is your favorite color?");
    }

    public function testQuestionType(): void {
        $this->assertEquals($this->my_polls[0]->getQuestionType(), "single-response-single-correct");
        $this->assertEquals($this->my_polls[1]->getQuestionType(), "single-response-multiple-correct");
        $this->assertEquals($this->my_polls[2]->getQuestionType(), "multiple-response-survey");
    }

    public function testResponses(): void {
        $this->assertEquals($this->my_polls[0]->getResponses(), [0 => 0, 1 => 1, 2 => 2]);
        $this->assertEquals($this->my_polls[0]->getResponsesWithKeys(), [0 => "Yes", 1 => "No", 2 => "Maybe"]);
        $this->assertEquals($this->my_polls[0]->getResponseString(0), "Yes");
        $this->assertEquals($this->my_polls[0]->getResponseString(1), "No");
        $this->assertEquals($this->my_polls[0]->getResponseString(2), "Maybe");
        $this->assertEquals($this->my_polls[0]->getResponseString(-1), "No Response");
        $this->assertEquals($this->my_polls[0]->getAllResponsesString([0 => 0, 1 => 1, 2 => 2]), "Yes, No, Maybe");
        $this->assertEquals($this->my_polls[0]->getAllResponsesString([0 => 0, 1 => 2]), "Yes, Maybe");
        $this->assertEquals($this->my_polls[0]->getAllResponsesString([0 => 1, 1 => 2]), "No, Maybe");
        $this->assertEquals($this->my_polls[0]->getAllResponsesString([0 => 0, 1 => 1]), "Yes, No");
        $this->assertEquals($this->my_polls[0]->getAllResponsesString([0 => 0]), "Yes");
        $this->assertEquals($this->my_polls[0]->getAllResponsesString([0 => 1]), "No");
        $this->assertEquals($this->my_polls[0]->getAllResponsesString([0 => 2]), "Maybe");

        $this->assertEquals($this->my_polls[1]->getResponses(), [0 => 0, 1 => 1, 2 => 2]);
        $this->assertEquals($this->my_polls[1]->getResponsesWithKeys(), [0 => "Absolutely", 1 => "No", 2 => "Perhaps"]);
        $this->assertEquals($this->my_polls[1]->getResponseString(0), "Absolutely");
        $this->assertEquals($this->my_polls[1]->getResponseString(1), "No");
        $this->assertEquals($this->my_polls[1]->getResponseString(2), "Perhaps");
        $this->assertEquals($this->my_polls[1]->getResponseString(-1), "No Response");
        $this->assertEquals($this->my_polls[1]->getAllResponsesString([0 => 0, 1 => 1, 2 => 2]), "Absolutely, No, Perhaps");
        $this->assertEquals($this->my_polls[1]->getAllResponsesString([0 => 0, 1 => 2]), "Absolutely, Perhaps");
        $this->assertEquals($this->my_polls[1]->getAllResponsesString([0 => 1, 1 => 2]), "No, Perhaps");
        $this->assertEquals($this->my_polls[1]->getAllResponsesString([0 => 0, 1 => 1]), "Absolutely, No");
        $this->assertEquals($this->my_polls[1]->getAllResponsesString([0 => 0]), "Absolutely");
        $this->assertEquals($this->my_polls[1]->getAllResponsesString([0 => 1]), "No");
        $this->assertEquals($this->my_polls[1]->getAllResponsesString([0 => 2]), "Perhaps");

        $this->assertEquals($this->my_polls[2]->getResponses(), [0 => 0, 1 => 1, 2 => 2, 3 => 3]);
        $this->assertEquals($this->my_polls[2]->getResponsesWithKeys(), [0 => "Red", 1 => "Blue", 2 => "Yellow", 3 => "Green"]);
        $this->assertEquals($this->my_polls[2]->getResponseString(0), "Red");
        $this->assertEquals($this->my_polls[2]->getResponseString(1), "Blue");
        $this->assertEquals($this->my_polls[2]->getResponseString(2), "Yellow");
        $this->assertEquals($this->my_polls[2]->getResponseString(3), "Green");
        $this->assertEquals($this->my_polls[2]->getResponseString(-1), "No Response");
        $this->assertEquals($this->my_polls[2]->getAllResponsesString([0 => 0, 1 => 1, 2 => 2, 3 => 3]), "Red, Blue, Yellow, Green");
        $this->assertEquals($this->my_polls[2]->getAllResponsesString([0 => 0, 1 => 2, 2 => 3]), "Red, Yellow, Green");
        $this->assertEquals($this->my_polls[2]->getAllResponsesString([0 => 0, 1 => 1]), "Red, Blue");
        $this->assertEquals($this->my_polls[2]->getAllResponsesString([0 => 0, 1 => 2]), "Red, Yellow");
        $this->assertEquals($this->my_polls[2]->getAllResponsesString([0 => 0, 1 => 3]), "Red, Green");
        $this->assertEquals($this->my_polls[2]->getAllResponsesString([0 => 1, 1 => 2]), "Blue, Yellow");
        $this->assertEquals($this->my_polls[2]->getAllResponsesString([0 => 1, 1 => 3]), "Blue, Green");
        $this->assertEquals($this->my_polls[2]->getAllResponsesString([0 => 2, 1 => 3]), "Yellow, Green");
        $this->assertEquals($this->my_polls[2]->getAllResponsesString([0 => 0]), "Red");
        $this->assertEquals($this->my_polls[2]->getAllResponsesString([0 => 1]), "Blue");
        $this->assertEquals($this->my_polls[2]->getAllResponsesString([0 => 2]), "Yellow");
        $this->assertEquals($this->my_polls[2]->getAllResponsesString([0 => 3]), "Green");
    }

    public function testAnswers(): void {
        $this->assertEquals($this->my_polls[0]->getAnswers(), [0 => 0]);
        $this->assertEquals($this->my_polls[1]->getAnswers(), [0 => 1, 1 => 2]);
        $this->assertEquals($this->my_polls[2]->getAnswers(), [0 => 0, 1 => 1, 2 => 2, 3 => 3]);
    }

    public function testUserResponses(): void {
        $this->assertEquals($this->my_polls[0]->getUserResponses(), ["bitdiddle" => [0 => 1], "aphacker" => [0 => 1]]);
        $this->assertEquals($this->my_polls[0]->getUserResponse("bitdiddle"), [0 => 1]);
        $this->assertEquals($this->my_polls[0]->getUserResponse("aphacker"), [0 => 1]);
        $this->assertEquals($this->my_polls[0]->getUserResponse("student"), null);

        $this->assertEquals($this->my_polls[1]->getUserResponses(), ["bitdiddle" => [0 => 2], "aphacker" => [0 => 0]]);
        $this->assertEquals($this->my_polls[1]->getUserResponse("bitdiddle"), [0 => 2]);
        $this->assertEquals($this->my_polls[1]->getUserResponse("aphacker"), [0 => 0]);
        $this->assertEquals($this->my_polls[1]->getUserResponse("student"), null);

        $this->assertEquals($this->my_polls[2]->getUserResponses(), ["bitdiddle" => [0 => 0, 1 => 2, 2 => 3], "aphacker" => [0 => 1, 1 => 3]]);
        $this->assertEquals($this->my_polls[2]->getUserResponse("bitdiddle"), [0 => 0, 1 => 2, 2 => 3]);
        $this->assertEquals($this->my_polls[2]->getUserResponse("aphacker"), [0 => 1, 1 => 3]);
        $this->assertEquals($this->my_polls[2]->getUserResponse("student"), null);
    }

    public function testReleaseDate(): void {
        $this->assertEquals($this->my_polls[0]->getReleaseDate(), "2021-01-11");
        $this->assertTrue($this->my_polls[0]->isInPast());
        $this->assertFalse($this->my_polls[0]->isInFuture());
        $this->assertFalse($this->my_polls[0]->isToday());

        $this->assertEquals($this->my_polls[1]->getReleaseDate(), "9999-12-31");
        $this->assertFalse($this->my_polls[1]->isInPast());
        $this->assertTrue($this->my_polls[1]->isInFuture());
        $this->assertFalse($this->my_polls[1]->isToday());

        $this->assertEquals($this->my_polls[2]->getReleaseDate(), date("Y-m-d"));
        $this->assertFalse($this->my_polls[2]->isInPast());
        $this->assertFalse($this->my_polls[2]->isInFuture());
        $this->assertTrue($this->my_polls[2]->isToday());
    }

    public function testStatus(): void {
        $this->assertFalse($this->my_polls[0]->isOpen());
        $this->assertTrue($this->my_polls[0]->isClosed());
        $this->assertFalse($this->my_polls[0]->isEnded());

        $this->assertTrue($this->my_polls[1]->isOpen());
        $this->assertFalse($this->my_polls[1]->isClosed());
        $this->assertFalse($this->my_polls[1]->isEnded());

        $this->assertFalse($this->my_polls[2]->isOpen());
        $this->assertFalse($this->my_polls[2]->isClosed());
        $this->assertTrue($this->my_polls[2]->isEnded());
    }

    public function testImagePath(): void {
        $this->assertEquals($this->my_polls[0]->getImagePath(), null);
        $this->assertEquals($this->my_polls[1]->getImagePath(), null);
        $this->assertEquals($this->my_polls[2]->getImagePath(), "/var/local/submitty/courses/s21/sample/uploads/polls/poll_image_3_colors.png");
    }
}
