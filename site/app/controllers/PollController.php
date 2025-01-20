<?php

namespace app\controllers;

use app\entities\poll\Option;
use app\entities\poll\Poll;
use app\entities\poll\Response;
use app\libraries\Core;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\routers\AccessControl;
use app\libraries\routers\Enabled;
use app\libraries\FileUtils;
use app\libraries\PollUtils;
use app\views\PollView;
use app\libraries\socket\Client;
use WebSocket;
use DateInterval;

/**
 * @Enabled("polls")
 */
class PollController extends AbstractController {
    public function __construct(Core $core) {
        parent::__construct($core);
    }

    #[Route("/courses/{_semester}/{_course}/polls", methods: ["GET"])]
    public function showPollsPage(): WebResponse {
        /** @var \app\repositories\poll\PollRepository */
        $repo = $this->core->getCourseEntityManager()->getRepository(Poll::class);

        if ($this->core->getUser()->accessAdmin()) {
            // Check if we have a saved cookie session with the dropdown states for each of the instructor polls sections
            $dropdown_states = ['today' => true, 'tomorrow' => true, 'old' => false, 'future' => false];
            foreach ($dropdown_states as $key => $val) {
                $cookie_key = $key . "_polls_dropdown";
                if (array_key_exists($cookie_key, $_COOKIE)) {
                    $dropdown_states[$key] = $_COOKIE[$cookie_key] === 'true';
                }
            }

            /** @var \app\entities\poll\Poll[] */
            $all_polls = [];
            $num_responses_by_poll = [];
            foreach ($repo->findAllWithNumResponses() as $row) {
                $all_polls[] = $row['poll'];
                $num_responses_by_poll[$row['poll']->getId()] = $row['num_responses'];
            }

            $todays_polls = [];
            $old_polls = [];
            $tomorrow_polls = [];
            $future_polls = [];
            /** @var Poll $poll */
            foreach ($all_polls as $poll) {
                if ($poll->getReleaseDate()->format('Y-m-d') === $this->core->getDateTimeNow()->format('Y-m-d')) {
                    $todays_polls[] = $poll;
                }
                elseif ($poll->getReleaseDate() < $this->core->getDateTimeNow()) {
                    $old_polls[] = $poll;
                }
                elseif (
                    $poll->getReleaseDate() > $this->core->getDateTimeNow()
                    && $poll->getReleaseDate()->format('Y-m-d') === $this->core->getDateTimeNow()->modify('+1 day')->format('Y-m-d')
                ) {
                    $tomorrow_polls[] = $poll;
                }
                elseif ($poll->getReleaseDate() > $this->core->getDateTimeNow()) {
                    $future_polls[] = $poll;
                }
            }

            return new WebResponse(
                PollView::class,
                'showPollsInstructor',
                $todays_polls,
                $old_polls,
                $tomorrow_polls,
                $future_polls,
                $num_responses_by_poll,
                $dropdown_states,
            );
        }
        else { // Student view
            $todays_polls = [];
            $old_polls = [];
            /** @var Poll */
            foreach ($repo->findAllByStudentIDWithAllOptions($this->core->getUser()->getId()) as $poll) {
                if ($poll->getReleaseDate()->format('Y-m-d') === $this->core->getDateTimeNow()->format('Y-m-d')) {
                    $todays_polls[] = $poll;
                }
                elseif ($poll->getReleaseDate() < $this->core->getDateTimeNow()) {
                    $old_polls[] = $poll;
                }
            }

            return new WebResponse(
                PollView::class,
                'showPollsStudent',
                $todays_polls,
                $old_polls
            );
        }
    }

    /**
     * @return RedirectResponse|WebResponse
     */
    #[Route("/courses/{_semester}/{_course}/polls/{poll_id}", methods: ["GET"], requirements: ["poll_id" => "\d*"])]
    public function showPoll(string $poll_id) {
        if (!is_numeric($poll_id)) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }

        /** @var \app\repositories\poll\PollRepository */
        $repo = $this->core->getCourseEntityManager()->getRepository(Poll::class);

        $response_counts = [];

        if ($this->core->getUser()->accessAdmin()) {
            /** @var Poll|null */
            $poll = $repo->findByIDWithOptions(intval($poll_id));
            if ($poll === null) {
                $this->core->addErrorMessage("Invalid Poll ID");
                return new RedirectResponse($this->core->buildCourseUrl(['polls']));
            }
            /** @var \app\repositories\poll\OptionRepository */
            $option_repo = $this->core->getCourseEntityManager()->getRepository(Option::class);
            $response_counts = $option_repo->findByPollWithResponseCounts(intval($poll_id));
        }
        else {
            /** @var Poll|null */
            $poll = $repo->findByStudentID($this->core->getUser()->getId(), intval($poll_id));
            if ($poll === null) {
                $this->core->addErrorMessage("Invalid Poll ID");
                return new RedirectResponse($this->core->buildCourseUrl(['polls']));
            }
            if (!$poll->isVisible()) {
                $this->core->addErrorMessage("Poll is not available");
                return new RedirectResponse($this->core->buildCourseUrl(['polls']));
            }
            if ($poll->isHistogramAvailable()) {
                /** @var \app\repositories\poll\OptionRepository */
                $option_repo = $this->core->getCourseEntityManager()->getRepository(Option::class);
                $response_counts = $option_repo->findByPollWithResponseCounts(intval($poll_id));
            }
        }

        return new WebResponse(
            PollView::class,
            'showPoll',
            $poll,
            $response_counts
        );
    }

    /**
     * @AccessControl(role="INSTRUCTOR")
     */
    #[Route("/courses/{_semester}/{_course}/polls/newPoll", methods: ["GET"])]
    public function showNewPollPage(): WebResponse {
        return new WebResponse(
            PollView::class,
            'pollForm'
        );
    }


    /**
     * @AccessControl(role="INSTRUCTOR")
     */
    #[Route("/courses/{_semester}/{_course}/polls/newPoll", methods: ["POST"])]
    public function addNewPoll(): RedirectResponse {
        $em = $this->core->getCourseEntityManager();

        $fields = ['name', 'question', 'question_type', 'release_date', 'release_histogram', 'release_answer'];
        foreach ($fields as $field) {
            if (empty($_POST[$field])) {
                $this->core->addErrorMessage("Poll must fill out all fields");
                return new RedirectResponse($this->core->buildCourseUrl(['polls']));
            }
        }
        $date = \DateTime::createFromFormat("Y-m-d", $_POST["release_date"]);
        if ($date === false) {
            $this->core->addErrorMessage("Invalid poll release date");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }
        if (!in_array($_POST["question_type"], PollUtils::getPollTypes())) {
            $this->core->addErrorMessage("Invalid poll question type");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }

        if (!in_array($_POST["release_histogram"], PollUtils::getReleaseHistogramSettings())) {
                    $this->core->addErrorMessage("Invalid student histogram release setting");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }

        if (!in_array($_POST["release_answer"], PollUtils::getReleaseAnswerSettings())) {
                    $this->core->addErrorMessage("Invalid poll answer release setting");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }

        //set to 0 if it is not found in $_POST.
        $hours = intval($_POST['poll-hours'] ?? 0);
        $minutes = intval($_POST['poll-minutes'] ?? 0);
        $seconds = intval($_POST['poll-seconds'] ?? 0);
        $duration = new DateInterval("PT{$hours}H{$minutes}M{$seconds}S");
        //comparing with DateTimes because PHP doesn't support DateInterval comparison
        $UserInputDuration = $this->core->getDateTimeNow();
        $TwentyFourHourDateTime = clone $UserInputDuration;
        $UserInputDuration->add($duration);
        $twentyfourHourDuration = new DateInterval("PT24H");
        $TwentyFourHourDateTime->add($twentyfourHourDuration);
        if ($UserInputDuration > $TwentyFourHourDateTime) {
            $this->core->addErrorMessage("Exceeded 24 hour limit");
            return new RedirectResponse($this->core->buildCourseUrl(['polls/newPoll']));
        }
        if ($hours < 0 || $minutes < 0 || $seconds < 0) {
            $this->core->addErrorMessage('Invalid time given');
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }
        $poll = new Poll($_POST['name'], $_POST['question'], $_POST['question_type'], $duration, $date, $_POST['release_histogram'], $_POST["release_answer"], null, isset($_POST['poll-custom-options']));
        $em->persist($poll);

        // Need to run this after persist so that we can use getId() below
        if (isset($_FILES['image_file']) && $_FILES["image_file"]["name"] !== "") {
            // validate the uploaded file size
            $status = FileUtils::validateUploadedFiles($_FILES["image_file"]);
            if (!$status[0]["success"]) {
                $this->core->addErrorMessage("Failed to validate poll image: " . $status[0]["error"]);
            }
            else {
                $file = $_FILES["image_file"];
                // validate the uploaded file type is indeed an image
                if (!FileUtils::isValidImage($file["tmp_name"])) {
                    $this->core->addErrorMessage("Error: " . $file["name"] . " is not a valid image file. File was not successfully attached to poll '" . $_POST["name"] . "'.");
                }
                else {
                    $file_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "polls", "poll_image_" . $poll->getId() . "_" . $file["name"]);
                    move_uploaded_file($file["tmp_name"], $file_path);
                    $poll->setImagePath($file_path);
                }
            }
        }

        $answers = 0;

        foreach ($_POST['option'] as $option) {
            if (!isset($option['order']) || !isset($option['response'])) {
                $this->core->addErrorMessage("Error occurred in adding poll");
                return new RedirectResponse($this->core->buildCourseUrl(['polls']));
            }
            $option = new Option((int) $option['order'], $option['response'], isset($option['is_correct']) && $option['is_correct'] === 'on');
            if ($option->isCorrect()) {
                $answers++;
            }
            $poll->addOption($option);
            $em->persist($option);
        }

        if ($answers === 0) {
            $this->core->addErrorMessage("Polls must have at least one correct response");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }
        elseif ($_POST["question_type"] === "single-response-single-correct" && $answers > 1) {
            $this->core->addErrorMessage("Polls of type 'single-response-single-correct' must have exactly one correct response");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }
        elseif (in_array($_POST['question_type'], ["single-response-survey", "multiple-response-survey"]) && $answers !== count($poll->getOptions())) {
            $this->core->addErrorMessage("All responses of polls of type 'survey' must be marked at correct responses");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }

        $em->flush();

        $this->core->addSuccessMessage("Poll successfully added");
        return new RedirectResponse($this->core->buildCourseUrl(['polls']));
    }

    /**
     * @AccessControl(role="INSTRUCTOR")
     * @return RedirectResponse|WebResponse
     */
    #[Route("/courses/{_semester}/{_course}/polls/editPoll/{poll_id}", methods: ["GET"], requirements: ["poll_id" => "\d*", ])]
    public function editPoll($poll_id) {
        if (!isset($poll_id)) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }

        $poll = $this->core->getCourseEntityManager()->find(Poll::class, $poll_id);

        if ($poll === null) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }

        return new WebResponse(
            PollView::class,
            'pollForm',
            $poll
        );
    }

    /**
     * @AccessControl(role="INSTRUCTOR")
     */
    #[Route("/courses/{_semester}/{_course}/polls/editPoll/submitEdits", methods: ["POST"])]
    public function submitEdits(): RedirectResponse {
        $returnUrl = $this->core->buildCourseUrl(['polls']);
        $poll_id = (int) $_POST['poll_id'];
        if (empty($poll_id)) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return new RedirectResponse($returnUrl);
        }

        $em = $this->core->getCourseEntityManager();

        /** @var \app\repositories\poll\PollRepository */
        $repo = $em->getRepository(Poll::class);

        /** @var Poll|null */
        $poll = $repo->findByIDWithOptions($poll_id);

        if ($poll === null) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return new RedirectResponse($returnUrl);
        }

        $fields = ['name', 'question', 'question_type', 'release_date', 'release_histogram', 'release_answer'];
        foreach ($fields as $field) {
            if (empty($_POST[$field])) {
                $this->core->addErrorMessage("Poll must fill out all fields");
                return new RedirectResponse($this->core->buildCourseUrl(['polls']));
            }
        }
        $date = \DateTime::createFromFormat("Y-m-d", $_POST["release_date"]);
        $hours = intval($_POST['poll-hours'] ?? 0);
        $minutes = intval($_POST['poll-minutes'] ?? 0);
        $seconds = intval($_POST['poll-seconds'] ?? 0);
        $enableTimer = isset($_POST['enable-timer']);
        if (!$enableTimer) {
            $hours = 0;
            $minutes = 0;
            $seconds = 0;
        }
        $prevHours = $poll->getDuration()->h;
        $prevMinutes = $poll->getDuration()->i;
        $prevSeconds = $poll->getDuration()->s;
        $resetDuration = true;
        if ($hours === $prevHours && $minutes === $prevMinutes && $seconds === $prevSeconds) {
            $resetDuration = false;
        }
        $newDuration = new DateInterval("PT{$hours}H{$minutes}M{$seconds}S");
        //comparing with DateTimes because PHP doesn't support DateInterval comparison
        $UserInputDuration = $this->core->getDateTimeNow();
        $TwentyFourHourDateTime = clone $UserInputDuration;
        $UserInputDuration->add($newDuration);
        $twentyfourHourDuration = new DateInterval("PT24H");
        $TwentyFourHourDateTime->add($twentyfourHourDuration);
        if ($UserInputDuration > $TwentyFourHourDateTime) {
            $this->core->addErrorMessage("Exceeded 24 hour limit");
            return new RedirectResponse($this->core->buildCourseUrl(['polls/newPoll']));
        }
        if ($hours < 0 || $minutes < 0 || $seconds < 0) {
            $this->core->addErrorMessage('Invalid time given');
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }
        if ($poll->isOpen() && $resetDuration) {
            if ($newDuration->h > 0 || $newDuration->i > 0 || $newDuration->s > 0 || $newDuration->days > 0 || $newDuration->m > 0 || $newDuration->y > 0) {
                $endDate = $this->core->getDateTimeNow();
                $endDate->add($newDuration);
                $poll->setEndTime($endDate);
            }
            else {
                // Timer Disabled
                $poll->setEndTime(null);
            }
        }
        if ($date === false) {
            $this->core->addErrorMessage("Invalid poll release date");
            return new RedirectResponse($returnUrl);
        }
        if (!in_array($_POST["question_type"], PollUtils::getPollTypes())) {
            $this->core->addErrorMessage("Invalid poll question type");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }
        $poll->setName($_POST['name']);
        $poll->setQuestion($_POST['question']);
        $poll->setQuestionType($_POST['question_type']);
        $poll->setDuration($newDuration);
        $poll->setReleaseDate($date);
        $poll->setReleaseHistogram($_POST['release_histogram']);
        $poll->setReleaseAnswer($_POST['release_answer']);
        $poll->setAllowsCustomOptions(isset($_POST['poll-custom-options']));

        if (isset($_FILES['image_file']) && $_FILES["image_file"]["name"] !== "") {
            $file = $_FILES["image_file"];
            // validate file size
            $status = FileUtils::validateUploadedFiles($file);
            if (!$status[0]["success"]) {
                $this->core->addErrorMessage("Failed to validate uploads " . $status[0]["error"]);
            }
            elseif (!FileUtils::isValidImage($file["tmp_name"])) {
                // validate file type
                $this->core->addErrorMessage("Error: " . $file["name"] . " is not a valid image file. Image was not successfully updated in poll '" . $_POST["name"] . "'.");
                // reject the new image, but keep the old one
            }
            else {
                $current_file_path = $poll->getImagePath();
                if ($current_file_path !== null) {
                    unlink($current_file_path);
                }
                $file_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "polls", "poll_image_" . $_POST["poll_id"] . "_" . $_FILES["image_file"]["name"]);
                move_uploaded_file($file["tmp_name"], $file_path);
                $poll->setImagePath($file_path);
            }
        }
        elseif (!isset($_POST['keep_image'])) {
            $poll->setImagePath(null);
        }

        $keep_ids = [];

        $answers = 0;

        foreach ($_POST['option'] as $option) {
            if (!isset($option['order']) || !isset($option['response'])) {
                $this->core->addErrorMessage("Error occurred in adding poll");
                return new RedirectResponse($this->core->buildCourseUrl(['polls']));
            }
            $id = (int) $option['id'];
            $found = false;
            foreach ($poll->getOptions() as $poll_option) {
                if ($poll_option->getId() === $id) {
                    $poll_option->setOrderId((int) $option['order']);
                    $poll_option->setResponse($option['response']);
                    $poll_option->setCorrect(isset($option['is_correct']) && $option['is_correct'] === 'on');
                    if ($poll_option->isCorrect()) {
                        $answers++;
                    }
                    $found = true;
                    $keep_ids[] = $id;
                    break;
                }
            }
            if (!$found) {
                $option = new Option((int) $option['order'], $option['response'], isset($option['is_correct']) && $option['is_correct'] === 'on');
                if ($option->isCorrect()) {
                    $answers++;
                }
                $poll->addOption($option);
                $em->persist($option);
                $keep_ids[] = $option->getId();
            }
        }

        foreach ($poll->getOptions() as $poll_option) {
            if (!in_array($poll_option->getId(), $keep_ids)) {
                if ($poll_option->hasUserResponses()) {
                    $this->core->addErrorMessage("Error occurred in editing poll: attempt to delete response option that has already been submitted as an answer");
                    return new RedirectResponse($returnUrl);
                }
                $poll->removeOption($poll_option);
                $em->remove($poll_option);
            }
        }

        if ($answers === 0) {
            $this->core->addErrorMessage("Polls must have at least one correct response");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }
        elseif ($_POST["question_type"] === "single-response-single-correct" && $answers > 1) {
            $this->core->addErrorMessage("Polls of type 'single-response-single-correct' must have exactly one correct response");
            new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }
        elseif ((($_POST["question_type"] === "single-response-survey") || ($_POST["question_type"] === "multiple-response-survey")) && $answers !== count($poll->getOptions())) {
            $this->core->addErrorMessage("All responses of polls of type 'survey' must be marked at correct responses");
            new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }

        $em->flush();

        $web_socket_message = ['type' => 'poll_updated', 'poll_id' => $poll_id, 'socket' => 'student', 'message' => 'Poll updated'];
        $this->sendSocketMessage($web_socket_message);
        $this->core->addSuccessMessage("Poll successfully edited");

        return new RedirectResponse($returnUrl);
    }

    /**
     * @AccessControl(role="INSTRUCTOR")
     */
    #[Route("/courses/{_semester}/{_course}/polls/setOpen", methods: ["POST"])]
    public function openPoll(): RedirectResponse {
        $poll_id = intval($_POST['poll_id'] ?? -1);
        $em = $this->core->getCourseEntityManager();
        /** @var Poll|null */
        $poll = $em->find(Poll::class, $poll_id);
        if ($poll === null) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }
        $poll->setVisible();
        $duration = $poll->getDuration();
        if ($duration->h > 0 || $duration->i > 0 || $duration->s > 0 || $duration->days > 0 || $duration->m > 0 || $duration->y > 0) {
            $end_time = $this->core->getDateTimeNow();
            $end_time->add($duration);
            $poll->setEndTime($end_time);
        }
        else {
            //If duration is 0, it means that the user wants to manually close it.
            $end_time = null;
            $poll->setEndTime($end_time);
        }
        $em->flush();

        $web_socket_message = ['type' => 'poll_opened', 'poll_id' => $poll_id, 'socket' => 'student', 'message' => 'Poll opened'];
        $this->sendSocketMessage($web_socket_message);

        return new RedirectResponse($this->core->buildCourseUrl(['polls']));
    }

    #[Route("/courses/{_semester}/{_course}/polls/addCustomResponse", methods: ["POST"])]
    public function addCustomResponse(): JsonResponse {
        $poll_id = intval($_POST['poll_id'] ?? -1);
        $poll_response = $_POST['custom-response'] ?? '';
        $user_id = $this->core->getUser()->getId();
        $em = $this->core->getCourseEntityManager();
        $poll_repo = $em->getRepository(Poll::class);
        $option_repo = $em->getRepository(Option::class);
        /** @var Poll|null */
        $poll = $poll_repo->find($poll_id);
        if ($poll === null) {
            return JsonResponse::getFailResponse("Invalid Poll ID");
        }
        elseif (!$poll->isOpen() && !$this->core->getUser()->accessFaculty()) {
            return JsonResponse::getFailResponse("Poll is closed");
        }
        elseif (trim($poll_response) === '') {
            return JsonResponse::getFailResponse("No associated text provided for custom response");
        }
        elseif ($poll->getAllowsCustomResponses() === false) {
            return JsonResponse::getFailResponse("Poll is currently not accepting custom responses");
        }
        elseif ($option_repo->existsByPollAndResponse($poll_id, $poll_response)) {
            return JsonResponse::getFailResponse("A similar response already exists");
        }

        $custom_poll_option = new Option($poll->getOptions()->count(), $poll_response, $poll->isSurvey(), $user_id);
        $poll->addOption($custom_poll_option);
        $em->persist($custom_poll_option);

        $response = new Response($user_id);
        $poll->addResponse($response, $custom_poll_option->getId());
        $em->persist($response);
        $em->flush();

        return JsonResponse::getSuccessResponse(["message" => "Successfully added custom response"]);
    }

    #[Route("/courses/{_semester}/{_course}/polls/removeCustomResponse", methods: ["POST"])]
    public function removeCustomResponse(): JsonResponse {
        $poll_id = intval($_POST['poll_id'] ?? -1);
        $option_id = intval($_POST['option_id'] ?? -1);
        $user_id = $this->core->getUser()->getId();
        $em = $this->core->getCourseEntityManager();
        $poll_repo = $em->getRepository(Poll::class);
        /** @var Poll|null */
        $poll = $poll_repo->find($poll_id);
        if ($poll === null) {
            return JsonResponse::getErrorResponse("Invalid Poll ID");
        }
        elseif (!$poll->isOpen() && !$this->core->getUser()->accessFaculty()) {
            return JsonResponse::getFailResponse("Poll is closed");
        }

        /** @var Option|null */
        $custom_option = $this->core->getCourseEntityManager()->find(Option::class, $option_id);
        if ($custom_option === null) {
            return JsonResponse::getErrorResponse("Could not find custom response");
        }
        elseif ($custom_option->getAuthorId() !== $user_id && !$this->core->getUser()->accessFaculty()) {
            return JsonResponse::getErrorResponse("You have no access to remove this custom response");
        }
        elseif ($custom_option->getUserResponses()->count() > 1 || ($custom_option->getUserResponses()->count() === 1 && $custom_option->getUserResponses()->first()->getStudentId() !== $user_id)) {
            return JsonResponse::getErrorResponse("Cannot delete response option that has already been submitted as an answer by another individual");
        }

        foreach ($custom_option->getUserResponses() as $response) {
            $em->remove($response);
        }
        $poll->removeOption($custom_option);
        $em->remove($custom_option);
        $em->persist($poll);
        $em->flush();

        return JsonResponse::getSuccessResponse(["message" => "Successfully removed custom response"]);
    }

    /**
     * @AccessControl(role="INSTRUCTOR")
     */
    #[Route("/courses/{_semester}/{_course}/polls/setEnded", methods: ["POST"])]
    public function endPoll(): RedirectResponse {
        $poll_id = intval($_POST['poll_id'] ?? -1);
        $em = $this->core->getCourseEntityManager();
        /** @var Poll|null */
        $poll = $em->find(Poll::class, $poll_id);
        if ($poll === null) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }
        $poll->setOpen();
        $poll->setEndTime($this->core->getDateTimeNow());
        $em->flush();

        $web_socket_message = ['type' => 'poll_ended', 'poll_id' => $poll_id, 'socket' => 'student', 'message' => 'Poll ended'];
        $this->sendSocketMessage($web_socket_message);

        return new RedirectResponse($this->core->buildCourseUrl(['polls']));
    }

    /**
     * @AccessControl(role="INSTRUCTOR")
     */
    #[Route("/courses/{_semester}/{_course}/polls/setClosed", methods: ["POST"])]
    public function closePoll(): RedirectResponse {
        $poll_id = intval($_POST['poll_id'] ?? -1);
        $em = $this->core->getCourseEntityManager();
        /** @var Poll|null */
        $poll = $em->find(Poll::class, $poll_id);
        if ($poll === null) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }
        $poll->setClosed();
        $em->flush();

        $web_socket_message = ['type' => 'poll_closed', 'poll_id' => $poll_id, 'socket' => 'student', 'message' => 'Poll closed'];
        $this->sendSocketMessage($web_socket_message);

        return new RedirectResponse($this->core->buildCourseUrl(['polls']));
    }

    #[Route("/courses/{_semester}/{_course}/polls/submitResponse", methods: ["POST"])]
    public function submitResponse(): RedirectResponse {
        $em = $this->core->getCourseEntityManager();

        $poll_id = (int) $_POST['poll_id'];
        if (empty($poll_id)) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }

        /** @var \app\repositories\poll\PollRepository */
        $repo = $em->getRepository(Poll::class);
        /** @var Poll|null */
        $poll = $repo->findByStudentID($this->core->getUser()->getId(), $poll_id);
        if ($poll === null) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }

        if (!$poll->isOpen()) {
            $this->core->addErrorMessage("Poll is closed");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }
        if (!array_key_exists("answers", $_POST) && PollUtils::isSingleResponse($poll->getQuestionType())) {
            // Answer must be given for single-response ("no response" counts as a response)
            $this->core->addErrorMessage("No answer given");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }

        if (PollUtils::isSingleResponse($poll->getQuestionType()) && count($_POST['answers']) > 1) {
            $this->core->addErrorMessage('Single response polls can only have one response');
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }

        $user_id = $this->core->getUser()->getId();
        $web_socket_message = ['type' => 'update_histogram', 'poll_id' => $poll_id, 'socket' => 'instructor', 'message' => []];

        foreach ($poll->getUserResponses() as $response) {
            $em->remove($response);
        }
        if (array_key_exists("answers", $_POST) && $_POST['answers'][0] !== '-1') {
            foreach ($_POST['answers'] as $option_id) {
                $response = new Response($user_id);
                $poll->addResponse($response, $option_id);
                $em->persist($response);
            }
        }

        $em->flush();

        foreach ($poll->getOptions() as $option) {
            $web_socket_message['message'][$option->getResponse()] = $option->getUserResponses()->count();
        }

        $this->sendSocketMessage($web_socket_message);
        $this->core->addSuccessMessage("Poll response recorded");
        return new RedirectResponse($this->core->buildCourseUrl(['polls']));
    }

    /**
     * @AccessControl(role="INSTRUCTOR")
     */
    #[Route("/courses/{_semester}/{_course}/polls/deletePoll", methods: ["POST"])]
    public function deletePoll(): JsonResponse {
        $poll_id = intval($_POST['poll_id'] ?? -1);
        $em = $this->core->getCourseEntityManager();

        /** @var \app\repositories\poll\PollRepository */
        $repo = $em->getRepository(Poll::class);

        /** @var Poll|null */
        $poll = $repo->findByIDWithOptions($poll_id);
        if ($poll === null) {
            return JsonResponse::getFailResponse('Invalid Poll ID');
        }

        if ($poll->getImagePath() !== null) {
            unlink($poll->getImagePath());
        }

        foreach ($poll->getUserResponses() as $response) {
            $em->remove($response);
        }

        foreach ($poll->getOptions() as $option) {
            $poll->removeOption($option);
        }

        $em->remove($poll);
        $em->flush();

        return JsonResponse::getSuccessResponse();
    }

    /**
     * @AccessControl(role="INSTRUCTOR")
     * @return RedirectResponse|WebResponse
     */
    #[Route("/courses/{_semester}/{_course}/polls/viewResults/{poll_id}", methods: ["GET"], requirements: ["poll_id" => "\d*"])]
    public function viewResults($poll_id) {
        if (!isset($poll_id)) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }
        $poll = $this->core->getCourseEntityManager()->find(Poll::class, $poll_id);
        if ($poll === null) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return new RedirectResponse($this->core->buildCourseUrl(['polls']));
        }
        return new WebResponse(
            PollView::class,
            'viewResults',
            $poll
        );
    }

    /**
     * @AccessControl(role="INSTRUCTOR")
     */
    #[Route("/courses/{_semester}/{_course}/polls/hasAnswers", methods: ["POST"])]
    public function hasAnswers() {
        $option_id  = (int) $_POST['option_id'];
        if (empty($option_id)) {
            return JsonResponse::getFailResponse('Invalid option id');
        }
        /** @var Option|null */
        $option = $this->core->getCourseEntityManager()->find(Option::class, $option_id);
        if ($option === null) {
            return JsonResponse::getFailResponse('Invalid poll id');
        }
        return JsonResponse::getSuccessResponse($option->hasUserResponses());
    }

    /**
     * @AccessControl(role="INSTRUCTOR")
     */
    #[Route("/courses/{_semester}/{_course}/polls/export", methods: ["GET"])]
    public function getPollExportData() {
        /** @var Poll[] */
        $polls = $this->core->getCourseEntityManager()->getRepository(Poll::class)->findAll();
        $file_name = date("Y-m-d") . "_" . $this->core->getConfig()->getTerm() . "_" . $this->core->getConfig()->getCourse() . "_" . "poll_questions" . ".json";
        $data = FileUtils::encodeJson(PollUtils::getPollExportData($polls));
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
     * @AccessControl(role="INSTRUCTOR")
     */
    #[Route("/courses/{_semester}/{_course}/polls/import", methods: ["POST"])]
    public function importPollsFromJSON(): RedirectResponse {
        $em = $this->core->getCourseEntityManager();
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
            /*  Polls that were exported before this feature was
                implemented don't have this data. At the time, there
                only existed questions of type single response. */
            $question_type = array_key_exists("question_type", $poll) ? $poll['question_type'] : 'single-response-multiple-correct';
            $poll_entity = new Poll($poll['name'], $poll['question'], $question_type, new \DateInterval($poll['duration']), \DateTime::createFromFormat("Y-m-d", $poll['release_date']), $poll['release_histogram'], $poll['release_answer'], $poll['image_path'], $poll['allows_custom']);

            $em->persist($poll_entity);
            $order = 0;
            foreach ($poll['responses'] as $id => $response) {
                $option = new Option($order, $response, in_array($id, $poll['correct_responses']));
                $poll_entity->addOption($option);
                $em->persist($option);
                $order++;
            }
            $num_imported = $num_imported + 1;
        }

        $em->flush();

        if ($num_errors === 0) {
            $this->core->addSuccessMessage("Successfully imported " . $num_imported . " polls");
        }
        else {
            $this->core->addErrorMessage("Successfully imported " . $num_imported . " polls. Errors occurred in " . $num_errors . " polls");
        }
        return new RedirectResponse($this->core->buildCourseUrl(['polls']));
    }

    /**
     * This method opens a WebSocket client and sends a message containing corresponding poll updates
     */
    private function sendSocketMessage(mixed $msg_array): void {
        $msg_array['user_id'] = $this->core->getUser()->getId();
        $msg_array['page'] = $this->core->getConfig()->getTerm() . '-' . $this->core->getConfig()->getCourse() . "-polls-" .  $msg_array['poll_id'] . '-' . $msg_array['socket'];

        try {
            $client = new Client($this->core);
            $client->json_send($msg_array);
        }
        catch (WebSocket\ConnectionException $e) {
            $this->core->addNoticeMessage("WebSocket Server is down, page won't load dynamically.");
        }
    }
}
