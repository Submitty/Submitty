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
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\libraries\PollUtils;
use app\models\PollModel;

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
        if (!isset($_POST["response_count"]) || !isset($_POST["name"]) || !isset($_POST["question"])  || !isset($_POST["question-type"]) || !isset($_POST["release_date"])) {
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

        if (count($answers) == 0) {
            $this->core->addErrorMessage("Polls must have at least one correct response");
            new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }

        $poll_id = $this->core->getQueries()->addNewPoll($_POST["name"], $_POST["question"], $_POST["question-type"], $responses, $answers, $_POST["release_date"], $orders);
        $file_path = null;
        if (isset($_FILES['image_file']) && $_FILES["image_file"]["name"] !== "") {
            // validate the uploaded file size
            $status = FileUtils::validateUploadedFiles($_FILES["image_file"]);
            if (!$status[0]["success"]) {
                $this->core->getOutput()->renderResultMessage("Failed to validate uploads " . $status[0]["error"], false);
            }
            else {
                $file = $_FILES["image_file"];
                // validate the uploaded file type is indeed an image
                if (!FileUtils::isValidImage($file["tmp_name"])) {
                    $this->core->getOutput()->renderResultMessage("Error: " . $file["name"] . " is not a valid image file. File was not successfully attached to poll '" . $_POST["name"] . "'.", false);
                }
                else {
                    $file_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "polls", "poll_image_" . $poll_id . "_" . $file["name"]);
                    move_uploaded_file($file["tmp_name"], $file_path);
                    $this->core->getQueries()->setPollImage($poll_id, $file_path);
                }
            }
        }

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
            // FIXME: make this work for when we have only one answer, and also for when we have multiple
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
     */
    public function submitEdits(): RedirectResponse {
        $returnUrl = $this->core->buildCourseUrl(['polls']);
        if (!isset($_POST["poll_id"])) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return new RedirectResponse($returnUrl);
        }

        $poll = $this->core->getQueries()->getPoll($_POST['poll_id']);

        if ($poll === null) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return new RedirectResponse($returnUrl);
        }
        if (!isset($_POST["response_count"]) || !isset($_POST["name"]) || !isset($_POST["question"]) || !isset($_POST["question-type"]) || !isset($_POST["release_date"])) {
            $this->core->addErrorMessage("Error occured in editing poll");
            return new RedirectResponse($returnUrl);
        }
        if ($_POST["response_count"] <= 0 || $_POST["name"] == "" || $_POST["question"] == "" || $_POST["release_date"] == "") {
            $this->core->addErrorMessage("Poll must fill out all fields, and have at least one option");
            return new RedirectResponse($returnUrl);
        }
        $date = \DateTime::createFromFormat("Y-m-d", $_POST["release_date"]);
        if ($date === false) {
            $this->core->addErrorMessage("Invalid poll release date");
            return new RedirectResponse($returnUrl);
        }
        $file_path = null;
        if (isset($_FILES['image_file']) && $_FILES["image_file"]["name"] !== "") {
            $file = $_FILES["image_file"];
            // validate file size
            $status = FileUtils::validateUploadedFiles($file);
            if (!$status[0]["success"]) {
                $this->core->getOutput()->renderResultMessage("Failed to validate uploads " . $status[0]["error"], false);
            }
            elseif (!FileUtils::isValidImage($file["tmp_name"])) {
                // validate file type
                $this->core->getOutput()->renderResultMessage("Error: " . $file["name"] . " is not a valid image file. Image was not successfully updated in poll '" . $_POST["name"] . "'.", false);
                // reject the new image, but keep the old one
                $file_path = $poll->getImagePath();
            }
            else {
                $current_file_path = $poll->getImagePath();
                if ($current_file_path !== null) {
                    unlink($current_file_path);
                }
                $file_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "polls", "poll_image_" . $_POST["poll_id"] . "_" . $_FILES["image_file"]["name"]);
                move_uploaded_file($file["tmp_name"], $file_path);
            }
        }
        elseif (isset($_POST['keep_image'])) {
            $file_path = $poll->getImagePath();
        }
        $response_count = $_POST["response_count"];
        $responses = [];
        $answers = [];
        $orders = [];
        for ($i = 0; $i < $response_count; $i++) {
            if (!isset($_POST["option_id_" . $i]) || !isset($_POST["response_" . $i]) || !isset($_POST["order_" . $i])) {
                $this->core->addErrorMessage("Error occured in adding poll");
                return new RedirectResponse($returnUrl);
            }
            $responses[$_POST["option_id_" . $i]] = $_POST["response_" . $i];
            $orders[$_POST["option_id_" . $i]] = $_POST["order_" . $i];
            if (isset($_POST["is_correct_" . $i]) && $_POST["is_correct_" . $i] == "on") {
                $answers[] = $_POST["option_id_" . $i];
            }
        }
        if (count($answers) == 0) {
            $this->core->addErrorMessage("Polls must have at least one correct response");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }
        $this->core->getQueries()->editPoll($poll->getId(), $_POST["name"], $_POST["question"], $_POST["question-type"], $responses, $answers, $_POST["release_date"], $orders, $file_path);
        return new RedirectResponse($returnUrl);
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
        $image_path = $this->core->getQueries()->getPoll($_POST["poll_id"])->getImagePath();
        if ($image_path !== null) {
            unlink($image_path);
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
     * @Route("/courses/{_semester}/{_course}/polls/export", methods={"GET"})
     * @AccessControl(role="INSTRUCTOR")
     */
    public function getPollExportData() {
        $polls = PollUtils::getPollExportData($this->core->getQueries()->getPolls());
        $file_name = date("Y-m-d") . "_" . $this->core->getConfig()->getSemester() . "_" . $this->core->getConfig()->getCourse() . "_" . "poll_questions" . ".json";
        $data = FileUtils::encodeJson($polls);
        if ($data === false) {
            $this->core->addErrorMessage("Failed to export poll data. Please try again");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        header("Content-type: " . "application/json");
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        $this->core->getOutput()->renderString($data);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/polls/import", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     * @return RedirectResponse
     */
    public function importPollsFromJSON(): RedirectResponse {
        $filename = $_FILES["polls_file"]["tmp_name"];
        $polls = FileUtils::readJsonFile($filename);
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
            
            /*  Polls that were exported before this feature was 
                implemented don't have this data. At the time, there 
                only existed questions of type single reponse. */
            if (array_key_exists("question-type", $poll)) {
                $question_type = $poll["question-type"];
            } 
            else {
                question_type = "single-response";
            }

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
