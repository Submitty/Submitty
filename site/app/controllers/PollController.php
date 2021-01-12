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
use app\libraries\Utils;
use app\libraries\routers\FeatureFlag;
use app\libraries\PollUtils;

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
            // Check if we have a saved cookie session with the dropdown states for each of the instructor polls sections
            $dropdown_states = ['today' => true, 'old' => false, 'future' => false];
            foreach ($dropdown_states as $key => $val) {
                $cookie_key = $key . "_polls_dropdown";
                if (array_key_exists($cookie_key, $_COOKIE)) {
                    $dropdown_states[$key] = $_COOKIE[$cookie_key] === 'true';
                }
            }

            return MultiResponse::webOnlyResponse(
                new WebResponse(
                    'Poll',
                    'showPollsInstructor',
                    $this->core->getQueries()->getTodaysPolls(),
                    $this->core->getQueries()->getOlderPolls(),
                    $this->core->getQueries()->getFuturePolls(),
                    $dropdown_states
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
     * @Route("/courses/{_semester}/{_course}/polls/viewPoll/{poll_id}", methods={"GET"}, requirements={"poll_id": "\d*", })
     * @return MultiResponse
     */
    public function showPoll($poll_id) {
        if (!isset($poll_id)) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }
        $poll = $this->core->getQueries()->getPoll($poll_id);
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
     * @Route("/courses/{_semester}/{_course}/polls/setClosed", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     * @return RedirectResponse
     */
    public function closePoll() {
        if (!isset($_POST["poll_id"])) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }
        $this->core->getQueries()->closePoll($_POST["poll_id"]);

        return new RedirectResponse($this->core->buildCourseUrl(['polls']));
    }

    /**
     * @Route("/courses/{_semester}/{_course}/polls/updateDropdownStates", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     * @return JsonResponse
     */
    public function updateDropdownStates() {
        $user_id = $this->core->getUser()->getId();
        $expire_time = time() + (7 * 24 * 60 * 60); // 7 days from now
        Utils::setCookie($_POST["cookie_key"], $_POST["new_state"], $expire_time);
        return JsonResponse::getSuccessResponse($_COOKIE[$_POST["cookie_key"]]);
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
     * @Route("/courses/{_semester}/{_course}/polls/editPoll/{poll_id}", methods={"GET"}, requirements={"poll_id": "\d*", })
     * @AccessControl(role="INSTRUCTOR")
     * @return MultiResponse
     */
    public function editPoll($poll_id) {
        if (!isset($poll_id)) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }
        $poll = $this->core->getQueries()->getPoll($poll_id);

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
     * @return RedirectResponse
     */
    public function deletePoll() {
        if (!isset($_POST["poll_id"])) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }
        $this->core->getQueries()->deletePoll($_POST["poll_id"]);
        return new RedirectResponse($this->core->buildCourseUrl(['polls']));
    }

    /**
     * @Route("/courses/{_semester}/{_course}/polls/viewResults/{poll_id}", methods={"GET"}, requirements={"poll_id": "\d*", })
     * @AccessControl(role="INSTRUCTOR")
     * @return MultiResponse
     */
    public function viewResults($poll_id) {
        if (!isset($poll_id)) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }
        $poll = $this->core->getQueries()->getPoll($poll_id);
        $results = $this->core->getQueries()->getResults($poll_id);
        if ($poll == null) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
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

    /**
     * @Route("/courses/{_semester}/{_course}/polls/getPollExport", methods={"GET"})
     * @AccessControl(role="INSTRUCTOR")
     * @return JsonResponse
     */
    public function getPollExportData(): JsonResponse {
        $polls = $this->core->getQueries()->getPolls();
        return JsonResponse::getSuccessResponse(PollUtils::getPollExportData($polls));
    }

    /**
     * @Route("/courses/{_semester}/{_course}/polls/importPolls", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     * @return RedirectResponse
     */
    public function importPollsFromJSON(): RedirectResponse {
        $filename = $_FILES["polls_file"]["tmp_name"];
        if ($polls === false) {
            $this->core->addErrorMessage("Failed to read file. Make sure the file is the right format");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }
        $num_imported = 0;
        $num_errors = 0;
        foreach ($polls as $poll) {
            if (
                !array_key_exists("name", $poll)
                || !array_key_exists("question", $poll)
                || !array_key_exists("responses", $poll)
                || !array_key_exists("correct_responses", $poll)
                || !array_key_exists("release_date", $poll)
            ) {
                $num_errors = $num_errors + 1;
                continue;
            }
            $name = $poll["name"];
            $question = $poll["question"];
            $responses = [];
            $orders = [];
            $i = 0;
            foreach ($poll["responses"] as $id => $response) {
                $response_id = intval($id);
                $responses[$response_id] = $response;
                $orders[$response_id] = $i;
                $i = $i + 1;
            }
            $answers = $poll["correct_responses"];
            $release_date = $poll["release_date"];
            $this->core->getQueries()->addNewPoll($name, $question, $responses, $answers, $release_date, $orders);
            $num_imported = $num_imported + 1;
        }
        if ($num_errors === 0) {
            $this->core->addSuccessMessage("Successfully imported " . $num_imported . " polls");
        }
        else {
            $this->core->addErrorMessage("Successfully imported " . $num_imported . " polls. Errors occurred in " . $num_errors . " polls");
        }
        return new RedirectResponse($this->core->buildCourseUrl(['polls']));
    }
}
