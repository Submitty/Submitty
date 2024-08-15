<?php

namespace tests\app\entities\poll;

use app\entities\poll\Option;
use app\entities\poll\Poll;
use app\entities\poll\Response;
use app\libraries\DateUtils;
use DateTime;
use DateInterval;
use ReflectionProperty;
use tests\BaseUnitTest;

class PollTester extends BaseUnitTest {
    /** @var Poll[] */
    private $my_polls;

    public function setUp(): void {
        DateUtils::setTimezone(new \DateTimeZone("America/New_York"));

        $this->my_polls = [
            0 => new Poll(
                "Poll #1",
                "Is this the first poll?",
                "single-response-single-correct",
                new DateInterval("PT1H"),
                new DateTime("2021-01-11"),
                "never",
                "never"
            ),
            1 => new Poll(
                "Poll #2",
                "Is this the first poll?",
                "single-response-multiple-correct",
                new DateInterval("PT1M"),
                new DateTime("9999-12-31"),
                "always",
                "always"
            ),
            2 => new Poll(
                "Poll #3",
                "What is your favorite color?",
                "multiple-response-survey",
                new DateInterval("PT1M30S"),
                new DateTime('now'),
                "when_ended",
                "when_ended",
                "/var/local/submitty/courses/s24/sample/uploads/polls/poll_image_3_colors.png"
            ),
            3 => new Poll(
                "Poll #4",
                "How was your break?",
                "multiple-response-survey",
                new DateInterval("PT1M30S"),
                new DateTime('now'),
                "when_ended",
                "when_ended",
            )
        ];
        $this->my_polls[1]->setOpen();
        $this->my_polls[2]->setEnded();
        $this->my_polls[3]->setAllowsCustomOptions(true);
        $this->my_polls[3]->setOpen();

        $poll_property = new ReflectionProperty("app\\entities\\poll\\Poll", "id");
        $poll_property->setAccessible(true);
        $poll_property->setValue($this->my_polls[0], 0);
        $poll_property->setValue($this->my_polls[1], 1);
        $poll_property->setValue($this->my_polls[2], 2);
        $poll_property->setValue($this->my_polls[3], 3);

        $option_property = new ReflectionProperty("app\\entities\\poll\\Option", "id");
        $option_property->setAccessible(true);

        $option0 = new Option(0, "Yes", true);
        $option1 = new Option(1, "No", false);
        $option2 = new Option(2, "Maybe", false);
        $option_property->setValue($option0, 0);
        $option_property->setValue($option1, 1);
        $option_property->setValue($option2, 2);
        $this->my_polls[0]->addOption($option0);
        $this->my_polls[0]->addOption($option1);
        $this->my_polls[0]->addOption($option2);

        $option3 = new Option(0, "Absolutely", false);
        $option4 = new Option(1, "No", true);
        $option5 = new Option(2, "Perhaps", true);
        $option_property->setValue($option3, 3);
        $option_property->setValue($option4, 4);
        $option_property->setValue($option5, 5);
        $this->my_polls[1]->addOption($option3);
        $this->my_polls[1]->addOption($option4);
        $this->my_polls[1]->addOption($option5);

        $option6 = new Option(0, "Red", true);
        $option7 = new Option(1, "Blue", true);
        $option8 = new Option(2, "Yellow", true);
        $option9 = new Option(3, "Green", true);
        $option_property->setValue($option6, 6);
        $option_property->setValue($option7, 7);
        $option_property->setValue($option8, 8);
        $option_property->setValue($option9, 9);
        $this->my_polls[2]->addOption($option6);
        $this->my_polls[2]->addOption($option7);
        $this->my_polls[2]->addOption($option8);
        $this->my_polls[2]->addOption($option9);

        $option10 = new Option(0, "Good", true);
        $option11 = new Option(1, "Ok", true);
        $option_property->setValue($option10, 10);
        $option_property->setValue($option11, 11);
        $this->my_polls[3]->addOption($option10);
        $this->my_polls[3]->addOption($option11);
        $option12 = new Option($this->my_polls[3]->getOptions()->count(), "It was alright", $this->my_polls[3]->isSurvey(), 'aphacker');
        $option_property->setValue($option12, 12);
        $this->my_polls[3]->addOption($option12);

        $response_property = new ReflectionProperty("app\\entities\\poll\\Response", "id");
        $response_property->setAccessible(true);

        $response0 = new Response("bitdiddle");
        $response1 = new Response("aphacker");
        $response_property->setValue($response0, 0);
        $response_property->setValue($response1, 1);
        $this->my_polls[0]->addResponse($response0, 1);
        $this->my_polls[0]->addResponse($response1, 1);

        $response2 = new Response("bitdiddle");
        $response3 = new Response("aphacker");
        $response_property->setValue($response2, 2);
        $response_property->setValue($response3, 3);
        $this->my_polls[1]->addResponse($response2, 3);
        $this->my_polls[1]->addResponse($response3, 5);

        $response4 = new Response("bitdiddle");
        $response5 = new Response("bitdiddle");
        $response6 = new Response("bitdiddle");
        $response7 = new Response("aphacker");
        $response8 = new Response("aphacker");
        $response_property->setValue($response4, 4);
        $response_property->setValue($response5, 5);
        $response_property->setValue($response6, 6);
        $response_property->setValue($response7, 7);
        $response_property->setValue($response8, 8);
        $this->my_polls[2]->addResponse($response4, 6);
        $this->my_polls[2]->addResponse($response5, 8);
        $this->my_polls[2]->addResponse($response6, 9);
        $this->my_polls[2]->addResponse($response7, 7);
        $this->my_polls[2]->addResponse($response8, 9);

        $response9 = new Response("bitdiddle");
        $response10 = new Response("bitdiddle");
        $response11 = new Response("aphacker");
        $response12 = new Response("aphacker");
        $response_property->setValue($response9, 9);
        $response_property->setValue($response10, 10);
        $response_property->setValue($response11, 11);
        $response_property->setValue($response12, 12);
        $this->my_polls[3]->addResponse($response9, 10);
        $this->my_polls[3]->addResponse($response10, 12);
        $this->my_polls[3]->addResponse($response11, 11);
        $this->my_polls[3]->addResponse($response12, 12);
    }

    public function tearDown(): void {
    }

    public function testId(): void {
        $this->assertEquals($this->my_polls[0]->getId(), 0);
        $this->assertEquals($this->my_polls[1]->getId(), 1);
        $this->assertEquals($this->my_polls[2]->getId(), 2);
        $this->assertEquals($this->my_polls[3]->getId(), 3);
    }

    public function testName(): void {
        $this->assertEquals($this->my_polls[0]->getName(), "Poll #1");
        $this->assertEquals($this->my_polls[1]->getName(), "Poll #2");
        $this->assertEquals($this->my_polls[2]->getName(), "Poll #3");
        $this->assertEquals($this->my_polls[3]->getName(), "Poll #4");
    }

    public function testQuestion(): void {
        $this->assertEquals($this->my_polls[0]->getQuestion(), "Is this the first poll?");
        $this->assertEquals($this->my_polls[1]->getQuestion(), "Is this the first poll?");
        $this->assertEquals($this->my_polls[2]->getQuestion(), "What is your favorite color?");
        $this->assertEquals($this->my_polls[3]->getQuestion(), "How was your break?");
    }

    public function testQuestionType(): void {
        $this->assertEquals($this->my_polls[0]->getQuestionType(), "single-response-single-correct");
        $this->assertEquals($this->my_polls[1]->getQuestionType(), "single-response-multiple-correct");
        $this->assertEquals($this->my_polls[2]->getQuestionType(), "multiple-response-survey");
        $this->assertEquals($this->my_polls[3]->getQuestionType(), "multiple-response-survey");
    }

    public function testOptions(): void {
        $option_text = [];
        $order_id = [];
        $correct = [];
        foreach ($this->my_polls[0]->getOptions() as $o) {
            $option_text[] = $o->getResponse();
            $order_id[] = $o->getOrderId();
            $correct[] = $o->isCorrect();
        }
        $this->assertEquals(["Yes", "No", "Maybe"], $option_text);
        $this->assertEquals([0, 1, 2], $order_id);
        $this->assertEquals([true, false, false], $correct);

        $option_text = [];
        $order_id = [];
        $correct = [];
        foreach ($this->my_polls[1]->getOptions() as $o) {
            $option_text[] = $o->getResponse();
            $order_id[] = $o->getOrderId();
            $correct[] = $o->isCorrect();
        }
        $this->assertEquals(["Absolutely", "No", "Perhaps"], $option_text);
        $this->assertEquals([0, 1, 2], $order_id);
        $this->assertEquals([false, true, true], $correct);

        $option_text = [];
        $order_id = [];
        $correct = [];
        foreach ($this->my_polls[2]->getOptions() as $o) {
            $option_text[] = $o->getResponse();
            $order_id[] = $o->getOrderId();
            $correct[] = $o->isCorrect();
        }
        $this->assertEquals(["Red", "Blue", "Yellow", "Green"], $option_text);
        $this->assertEquals([0, 1, 2, 3], $order_id);
        $this->assertEquals([true, true, true, true], $correct);

        $option_text = [];
        $order_id = [];
        $correct = [];
        foreach ($this->my_polls[3]->getOptions() as $o) {
            $option_text[] = $o->getResponse();
            $order_id[] = $o->getOrderId();
            $correct[] = $o->isCorrect();
        }
        $this->assertEquals(["Good", "Ok", "It was alright"], $option_text);
        $this->assertEquals([0, 1, 2], $order_id);
        $this->assertEquals([true, true, true], $correct);

        $this->assertEquals("Yes", $this->my_polls[0]->getOptionById(0)->getResponse());
        $this->expectException(\RuntimeException::class);
        $this->my_polls[0]->getOptionById(5);
    }

    public function testUserResponses(): void {
        $student_id = [];
        $option_text = [];
        foreach ($this->my_polls[0]->getUserResponses() as $r) {
            $student_id[] = $r->getStudentId();
            $option_text[] = $r->getOption()->getResponse();
        }
        $this->assertEquals(["bitdiddle", "aphacker"], $student_id);
        $this->assertEquals(["No", "No"], $option_text);

        $student_id = [];
        $option_text = [];
        foreach ($this->my_polls[1]->getUserResponses() as $r) {
            $student_id[] = $r->getStudentId();
            $option_text[] = $r->getOption()->getResponse();
        }
        $this->assertEquals(["bitdiddle", "aphacker"], $student_id);
        $this->assertEquals(["Absolutely", "Perhaps"], $option_text);

        $student_id = [];
        $option_text = [];
        foreach ($this->my_polls[2]->getUserResponses() as $r) {
            $student_id[] = $r->getStudentId();
            $option_text[] = $r->getOption()->getResponse();
        }
        $this->assertEquals(["bitdiddle", "bitdiddle", "bitdiddle", "aphacker", "aphacker"], $student_id);
        $this->assertEquals(["Red", "Yellow", "Green", "Blue", "Green"], $option_text);

        $student_id = [];
        $option_text = [];
        foreach ($this->my_polls[3]->getUserResponses() as $r) {
            $student_id[] = $r->getStudentId();
            $option_text[] = $r->getOption()->getResponse();
        }
        $this->assertEquals(["bitdiddle", "bitdiddle", "aphacker", "aphacker"], $student_id);
        $this->assertEquals(["Good", "It was alright", "Ok", "It was alright"], $option_text);
    }

    public function testReleaseDate(): void {
        $this->assertEquals($this->my_polls[0]->getReleaseDate()->format("Y-m-d"), "2021-01-11");
        $this->assertEquals($this->my_polls[1]->getReleaseDate()->format("Y-m-d"), "9999-12-31");
        $this->assertEquals($this->my_polls[2]->getReleaseDate()->format("Y-m-d"), date("Y-m-d"));
        $this->assertEquals($this->my_polls[3]->getReleaseDate()->format("Y-m-d"), date("Y-m-d"));
    }

    public function testStatus(): void {

        $this->assertFalse($this->my_polls[0]->isOpen());
        $this->assertTrue($this->my_polls[0]->isClosed());
        $this->assertFalse($this->my_polls[0]->isEnded());

        //Set End time to NULL to test open state with no duration
        $this->my_polls[1]->setOpen();
        $this->my_polls[1]->setEndTime(null);
        $this->assertTrue($this->my_polls[1]->isOpen());
        $this->assertFalse($this->my_polls[1]->isClosed());
        $this->assertFalse($this->my_polls[1]->isEnded());

        $this->my_polls[2]->setEnded();
        $this->assertFalse($this->my_polls[2]->isOpen());
        $this->assertFalse($this->my_polls[2]->isClosed());
        $this->assertTrue($this->my_polls[2]->isEnded());

        $this->my_polls[2]->setClosed();
        $this->assertFalse($this->my_polls[2]->isOpen());
        $this->assertTrue($this->my_polls[2]->isClosed());
        $this->assertFalse($this->my_polls[2]->isEnded());

        $this->my_polls[1]->setEnded();
        $this->assertFalse($this->my_polls[1]->isOpen());
        $this->assertFalse($this->my_polls[1]->isClosed());
        $this->assertTrue($this->my_polls[1]->isEnded());

        $this->my_polls[2]->setEnded();
        $this->assertFalse($this->my_polls[2]->isOpen());
        $this->assertFalse($this->my_polls[2]->isClosed());
        $this->assertTrue($this->my_polls[2]->isEnded());

        $this->my_polls[3]->setEnded();
        $this->assertFalse($this->my_polls[3]->isOpen());
        $this->assertFalse($this->my_polls[3]->isClosed());
        $this->assertTrue($this->my_polls[3]->isEnded());
    }

    public function testDuration(): void {

        $this->assertEquals($this->my_polls[0]->getDuration()->h, 1);
        $this->assertEquals($this->my_polls[1]->getDuration()->i, 1);
        $this->assertEquals($this->my_polls[2]->getDuration()->i, 1);
        $this->assertEquals($this->my_polls[2]->getDuration()->s, 30);
        $newDateInterval = new DateInterval("PT10H");
        $this->my_polls[0]->setDuration($newDateInterval);
        $this->assertEquals($this->my_polls[0]->getDuration()->h, 10);
        $newDateInterval = new DateInterval("PT30S");
        $this->my_polls[1]->setDuration($newDateInterval);
        $this->assertEquals($this->my_polls[1]->getDuration()->s, 30);
        $newDateInterval = new DateInterval("PT0S");
        $this->my_polls[2]->setDuration($newDateInterval);
        // Testing empty duration
        $this->assertEquals($this->my_polls[2]->getDuration()->s, 0);
        $this->assertEquals($this->my_polls[2]->getDuration()->h, 0);
        $this->assertEquals($this->my_polls[2]->getDuration()->i, 0);
        $this->assertEquals($this->my_polls[2]->getDuration()->d, 0);
        $this->assertEquals($this->my_polls[2]->getDuration()->m, 0);
        $this->assertEquals($this->my_polls[2]->getDuration()->y, 0);
    }

    public function testImagePath(): void {
        $this->assertEquals($this->my_polls[0]->getImagePath(), null);
        $this->assertEquals($this->my_polls[1]->getImagePath(), null);
        $this->assertEquals($this->my_polls[2]->getImagePath(), "/var/local/submitty/courses/s24/sample/uploads/polls/poll_image_3_colors.png");
        $this->assertEquals($this->my_polls[3]->getImagePath(), null);
    }

    public function testEndTime(): void {

        //Testing Setters and Getters for EndTime
        $this->my_polls[0]->setEndTime(null);
        $this->assertEquals($this->my_polls[0]->getEndTime(), null);

        $this->my_polls[1]->setEnded();
        // SetEnded sets EndTime to current time (no way to check exact millisecond)
        $endtime = $this->my_polls[1]->getEndTime()->format("Y-m-d");
        $expectedDate = DateUtils::getDateTimeNow()->format("Y-m-d");
        $this->assertEquals($endtime, $expectedDate);

        $this->my_polls[2]->setEndTime(new DateTime('2025-10-03 05:30:00'));
        $this->assertEquals($this->my_polls[2]->getEndTime()->format("Y-m-d H:i:s"), "2025-10-03 05:30:00");

        $this->my_polls[0]->setEndTime(new DateTime(DateUtils::MAX_TIME));
        $this->assertEquals($this->my_polls[0]->getEndTime()->format("Y-m-d H:i:s"), DateUtils::MAX_TIME);
    }

    public function testHistogramRelease(): void {
        $this->assertEquals($this->my_polls[0]->getReleaseHistogram(), "never");
        $this->assertEquals($this->my_polls[1]->getReleaseHistogram(), "always");
        $this->assertEquals($this->my_polls[2]->getReleaseHistogram(), "when_ended");
        $this->assertEquals($this->my_polls[3]->getReleaseHistogram(), "when_ended");

        $this->my_polls[0]->setReleaseHistogram("always");
        $this->assertEquals("always", $this->my_polls[0]->getReleaseHistogram());

        $this->expectException(\RuntimeException::class);
        $this->my_polls[0]->setReleaseHistogram("aaaaaaaaa");
    }

    public function testAnswerRelease(): void {
        $this->assertEquals($this->my_polls[0]->getReleaseAnswer(), "never");
        $this->assertEquals($this->my_polls[1]->getReleaseAnswer(), "always");
        $this->assertEquals($this->my_polls[2]->getReleaseAnswer(), "when_ended");
        $this->assertEquals($this->my_polls[3]->getReleaseAnswer(), "when_ended");

        $this->my_polls[0]->setReleaseAnswer("always");
        $this->assertEquals("always", $this->my_polls[0]->getReleaseAnswer());

        $this->expectException(\RuntimeException::class);
        $this->my_polls[0]->setReleaseAnswer("AnInvalidStatusMessage");
    }
}
