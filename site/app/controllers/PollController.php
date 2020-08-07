<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\routers\AccessControl;
use app\libraries\DateUtils;
use app\libraries\routers\FeatureFlag;

/**
 * @FeatureFlag("polls")
 */
class PollController extends AbstractController {
    public function __construct(Core $core) {
        parent::__construct($core);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/polls", methods={"GET"})
     * @return MultiResponse
     */
    public function showPollsPage() {
        if ($this->core->getUser()->accessAdmin()) {
            return MultiResponse::webOnlyResponse(
                new WebResponse(
                    'Poll',
                    'showPollsInstructor',
                    $this->core->getQueries()->getTodaysPolls(),
                    $this->core->getQueries()->getOlderPolls(),
                    $this->core->getQueries()->getFuturePolls()
                )
            );
        }
        else {
            return MultiResponse::webOnlyResponse(
                new WebResponse(
                    'Poll',
                    'showPollsStudent',
                    $this->core->getQueries()->getTodaysPolls(),
                    $this->core->getQueries()->getOlderPolls()
                )
            );
        }
    }

    /**
     * @Route("/courses/{_semester}/{_course}/polls/viewPoll", methods={"POST"})
     * @return MultiResponse
     */
    public function showPoll() {
        if (!isset($_POST["poll_id"])) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }
        $poll = $this->core->getQueries()->getPoll($_POST["poll_id"]);
        if ($poll == null) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }
        if ($this->core->getUser()->accessAdmin()) {
            return MultiResponse::webOnlyResponse(
                new WebResponse(
                    'Poll',
                    'showPollInstructor',
                    $poll
                )
            );
        }
        else {
            return MultiResponse::webOnlyResponse(
                new WebResponse(
                    'Poll',
                    'showPollStudent',
                    $poll
                )
            );
        }
    }

    /**
     * @Route("/courses/{_semester}/{_course}/polls/newPoll", methods={"GET"})
     * @AccessControl(role="INSTRUCTOR")
     * @return MultiResponse
     */
    public function showNewPollPage() {
        return MultiResponse::webOnlyResponse(
            new WebResponse(
                'Poll',
                'showNewPollPage'
            )
        );
    }


    /**
     * @Route("/courses/{_semester}/{_course}/polls/newPoll", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     * @return MultiResponse
     */
    public function addNewPoll() {
        if (!isset($_POST["response_count"]) || !isset($_POST["name"]) || !isset($_POST["question"]) || !isset($_POST["release_date"])) {
            $this->core->addErrorMessage("Error occured in adding poll");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }
        if ($_POST["response_count"] <= 0 || $_POST["name"] == "" || $_POST["question"] == "" || $_POST["release_date"] == "") {
            $this->core->addErrorMessage("Poll must fill out all fields, and have at least one option");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }
        $date = \DateTime::createFromFormat("Y-m-d", $_POST["release_date"]);
        if ($date === false) {
            $this->core->addErrorMessage("Invalid poll release date");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }
        $response_count = $_POST["response_count"];
        $responses = [];
        $answers = [];
        $orders = [];
        for ($i = 0; $i < $response_count; $i++) {
            if (!isset($_POST["option_id_" . $i]) || !isset($_POST["response_" . $i]) || !isset($_POST["order_" . $i])) {
                $this->core->addErrorMessage("Error occured in adding poll");
                return MultiResponse::RedirectOnlyResponse(
                    new RedirectResponse($this->core->buildCourseUrl(['polls']))
                );
            }
            $responses[$_POST["option_id_" . $i]] = $_POST["response_" . $i];
            $orders[$_POST["option_id_" . $i]] = $_POST["order_" . $i];
            if (isset($_POST["is_correct_" . $i]) && $_POST["is_correct_" . $i] == "on") {
                $answers[] = $_POST["option_id_" . $i];
            }
        }
        $this->core->getQueries()->addNewPoll($_POST["name"], $_POST["question"], $responses, $answers, $_POST["release_date"], $orders);

        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['polls']))
        );
    }

    /**
     * @Route("/courses/{_semester}/{_course}/polls/setOpen", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     * @return MultiResponse
     */
    public function openPoll() {
        if (!isset($_POST["poll_id"])) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }
        $this->core->getQueries()->openPoll($_POST["poll_id"]);

        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['polls']))
        );
    }

    /**
     * @Route("/courses/{_semester}/{_course}/polls/setEnded", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     * @return MultiResponse
     */
    public function endPoll() {
        if (!isset($_POST["poll_id"])) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }
        $this->core->getQueries()->endPoll($_POST["poll_id"]);

        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['polls']))
        );
    }

    /**
     * @Route("/courses/{_semester}/{_course}/polls/submitResponse", methods={"POST"})
     * @return MultiResponse
     */
    public function submitResponse() {
        if (!isset($_POST["poll_id"])) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }
        if (!isset($_POST["answer"])) {
            $this->core->addErrorMessage("No answer given");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }
        $poll = $this->core->getQueries()->getPoll($_POST["poll_id"]);
        if ($poll == null) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }
        if ($poll->isOpen()) {
            if ($_POST["answer"] == "-1") {
                $this->core->getQueries()->deleteUserResponseIfExists($_POST["poll_id"]);
            }
            else {
                $this->core->getQueries()->submitResponse($_POST["poll_id"], $_POST["answer"]);
            }
        }
        else {
            $this->core->addErrorMessage("Poll is closed");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }

        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['polls']))
        );
    }

    /**
     * @Route("/courses/{_semester}/{_course}/polls/editPoll", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     * @return MultiResponse
     */
    public function editPoll() {
        if (!isset($_POST["poll_id"])) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }
        $poll = $this->core->getQueries()->getPoll($_POST["poll_id"]);

        if ($poll == null) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }

        return MultiResponse::webOnlyResponse(
            new WebResponse(
                'Poll',
                'editPoll',
                $poll
            )
        );
    }

    /**
     * @Route("/courses/{_semester}/{_course}/polls/editPoll/submitEdits", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     * @return MultiResponse
     */
    public function submitEdits() {
        if (!isset($_POST["poll_id"])) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }
        if (!isset($_POST["response_count"]) || !isset($_POST["name"]) || !isset($_POST["question"]) || !isset($_POST["release_date"])) {
            $this->core->addErrorMessage("Error occured in editing poll");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }
        if ($_POST["response_count"] <= 0 || $_POST["name"] == "" || $_POST["question"] == "" || $_POST["release_date"] == "") {
            $this->core->addErrorMessage("Poll must fill out all fields, and have at least one option");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }
        $date = \DateTime::createFromFormat("Y-m-d", $_POST["release_date"]);
        if ($date === false) {
            $this->core->addErrorMessage("Invalid poll release date");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }
        $response_count = $_POST["response_count"];
        $responses = [];
        $answers = [];
        $orders = [];
        for ($i = 0; $i < $response_count; $i++) {
            if (!isset($_POST["option_id_" . $i]) || !isset($_POST["response_" . $i]) || !isset($_POST["order_" . $i])) {
                $this->core->addErrorMessage("Error occured in adding poll");
                return MultiResponse::RedirectOnlyResponse(
                    new RedirectResponse($this->core->buildCourseUrl(['polls']))
                );
            }
            $responses[$_POST["option_id_" . $i]] = $_POST["response_" . $i];
            $orders[$_POST["option_id_" . $i]] = $_POST["order_" . $i];
            if (isset($_POST["is_correct_" . $i]) && $_POST["is_correct_" . $i] == "on") {
                $answers[] = $_POST["option_id_" . $i];
            }
        }
        $this->core->getQueries()->editPoll($_POST["poll_id"], $_POST["name"], $_POST["question"], $responses, $answers, $_POST["release_date"], $orders);

        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['polls']))
        );
    }

    /**
     * @Route("/courses/{_semester}/{_course}/polls/deletePoll", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     * @return MultiResponse
     */
    public function deletePoll() {
        if (!isset($_POST["poll_id"])) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }
        $this->core->getQueries()->deletePoll($_POST["poll_id"]);
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['polls']))
        );
    }

    /**
     * @Route("/courses/{_semester}/{_course}/polls/viewResults", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     * @return MultiResponse
     */
    public function viewResults() {
        if (!isset($_POST["poll_id"])) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }
        $poll = $this->core->getQueries()->getPoll($_POST["poll_id"]);
        $results = $this->core->getQueries()->getResults($_POST["poll_id"]);
        if ($poll == null) {
            if (!isset($_POST["poll_id"])) {
                $this->core->addErrorMessage("Invalid Poll ID");
                return MultiResponse::RedirectOnlyResponse(
                    new RedirectResponse($this->core->buildCourseUrl(['polls']))
                );
            }
        }
        return MultiResponse::webOnlyResponse(
            new WebResponse(
                'Poll',
                'viewResults',
                $poll,
                $results
            )
        );
    }
}
