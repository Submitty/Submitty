<?php

namespace app\controllers;

use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\models\OfficeHoursQueueModel;
use app\libraries\routers\AccessControl;
use app\libraries\routers\Enabled;
use app\libraries\socket\Client;
use app\libraries\Logger;
use WebSocket;

/**
 * Class OfficeHoursQueueController
 *
 * @Enabled("queue")
 */
class OfficeHoursQueueController extends AbstractController {
    /**
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/office_hours_queue", methods: ["GET"])]
    public function showQueue($full_history = false) {
        return MultiResponse::webOnlyResponse(
            new WebResponse(
                'OfficeHoursQueue',
                'showTheQueue',
                new OfficeHoursQueueModel($this->core, $full_history),
                $this->core->getQueries()->getAllUsers()
            )
        );
    }

    /**
     * @return MultiResponse
     */
    #[AccessControl(role: "LIMITED_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/office_hours_queue", methods: ["POST"])]
    public function openQueue() {
        if (empty($_POST['code'])) {
            $this->core->addErrorMessage("Missing queue name");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $require_contact_info = isset($_POST['require_contact_information']);

        //Replace whitespace with "_"
        $queue_code = trim($_POST['code']);
        $token = trim($_POST['token']) != "" ? trim($_POST['token']) : null;

        $re = '/^[\sa-zA-Z0-9_\-]+$/m';
        $matches_code = [0];
        preg_match_all($re, $queue_code, $matches_code, PREG_SET_ORDER, 0);
        $matches_token = [0];
        if ($token !== null) {
            preg_match_all($re, $token, $matches_token, PREG_SET_ORDER, 0);
        }
        if (count($matches_code) !== 1 || count($matches_token) !== 1) {
            $this->core->addErrorMessage('Queue name and secret code must only contain letters, numbers, spaces, "_", and "-"');
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }
        $regex_pattern = isset($_POST['regex']) ? trim($_POST['regex']) : '';
        if ($this->core->getQueries()->openQueue($queue_code, $token, $regex_pattern, $require_contact_info)) {
            $this->core->addSuccessMessage("New queue added");
            Logger::logQueueActivity($this->core->getConfig()->getTerm(), $this->core->getDisplayedCourseName(), $queue_code, "CREATED");
        }
        else {
            $this->core->addErrorMessage("Unable to add queue. Make sure you have a unique queue name");
        }

        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }


    /**
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/office_hours_queue/{queue_code}/add", methods: ["POST"])]
    public function addPerson($queue_code) {
        if (empty($_POST['name'])) {
            $this->core->addErrorMessage("Missing user's name");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if (empty($queue_code)) {
            $this->core->addErrorMessage("Missing queue name");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }


        $contact_info = null;
        if ($this->core->getQueries()->getQueueHasContactInformation($queue_code)) {
            if (!isset($_POST['contact_info'])) {
                $this->core->addErrorMessage("Missing contact info");
                return MultiResponse::RedirectOnlyResponse(
                    new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
                );
            }
            else {
                $contact_info = trim($_POST['contact_info']);
                //make sure contact information matches instructors regex pattern
                $regex_pattern = $this->core->getQueries()->getQueueRegex($queue_code)[0]['regex_pattern'];
                if ($regex_pattern !== '') {
                    $regex_pattern = '#' . $regex_pattern . '#';
                    if (preg_match($regex_pattern, $contact_info) == 0) {
                        $this->core->addErrorMessage("Invalid contact information format.  Please re-read the course-specific instructions about the necessary information you should provide when you join this office hours queue.");
                        return MultiResponse::RedirectOnlyResponse(
                            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
                        );
                    }
                }
            }
        }
        $queue_code = trim($queue_code);
        $token = trim($_POST['token'] ?? '');

        $validated_code = $this->core->getQueries()->getValidatedCode($queue_code, $token);
        if (!$validated_code) {
            $this->core->addErrorMessage("Invalid secret code");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if ($this->core->getQueries()->alreadyInAQueue()) {
            $this->core->addErrorMessage("You are already in the queue");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $this->core->getQueries()->addToQueue($validated_code, $this->core->getUser()->getId(), $_POST['name'], $contact_info);
        $this->sendSocketMessage(['type' => 'queue_update']);
        $this->core->addSuccessMessage("Added to queue");
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }


    /**
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/office_hours_queue/{queue_code}/remove", methods: ["POST"])]
    public function removePerson($queue_code) {
        if (empty($_POST['user_id'])) {
            $this->core->addErrorMessage("Missing user ID");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if (empty($queue_code)) {
            $this->core->addErrorMessage("Missing queue name");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if (!$this->core->getUser()->accessGrading() && $this->core->getUser()->getId() !== $_POST['user_id']) {
            $this->core->addErrorMessage("Permission denied to remove that person");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $remove_type = 'removed';//Mentor or ta removed you
        if ($this->core->getUser()->getId() === $_POST['user_id']) {
            $remove_type = 'self';//You removed yourself
        }


        $this->core->getQueries()->removeUserFromQueue($_POST['user_id'], $remove_type, $queue_code);
        $this->sendSocketMessage(['type' => 'full_update']);
        $this->core->addSuccessMessage("Removed from queue");
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    /**
     * @return RedirectResponse
     */
    #[Route("/courses/{_semester}/{_course}/office_hours_queue/{queue_code}/switch", methods: ["POST"])]
    public function switchQueue($queue_code) {
        $user_id = $this->core->getUser()->getId();

        //do all error checking before leaving previous queue
        //make sure they are already in a queue first
        if (!$this->core->getQueries()->alreadyInAQueue($user_id)) {
            $this->core->addErrorMessage("You aren't in a queue");
            return new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']));
        }

        //get the time they joined the previous queue
        $time_in = $this->core->getQueries()->getTimeJoinedQueue($user_id, $queue_code);
        $token = $_POST['token'];
        $new_queue_code = $_POST['code'];

        //check that the new token entered is correct
        $validated_code = $this->core->getQueries()->getValidatedCode($new_queue_code, $token);
        if (!$validated_code) {
            $this->core->addErrorMessage("Invalid secret code");
            return new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']));
        }

        if (empty($_POST['code'])) {
            $this->core->addErrorMessage("Missing queue name");
            return new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']));
        }

        $contact_info = null;
        if ($this->core->getQueries()->getQueueHasContactInformation($validated_code)) {
            if (!isset($_POST['contact_info'])) {
                $this->core->addErrorMessage("Missing contact info");
                return new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']));
            }
            else {
                $contact_info = trim($_POST['contact_info']);
                //make sure contact information matches instructors regex pattern
                $regex_pattern = $this->core->getQueries()->getQueueRegex($queue_code)[0]['regex_pattern'];
                if ($regex_pattern !== '') {
                    $regex_pattern = '#' . $regex_pattern . '#';
                    if (preg_match($regex_pattern, $contact_info) == 0) {
                        $this->core->addErrorMessage("Invalid contact information format.  Please re-read the course-specific instructions about the necessary information you should provide when you join this office hours queue.");
                        return new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']));
                    }
                }
            }
        }

        if (empty($_POST['name'])) {
            $this->core->addErrorMessage("Missing user's name");
            return new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']));
        }


        //remove them from current queue
        $this->core->getQueries()->removeUserFromQueue($user_id, 'self', $queue_code);

        //add to new queue
        $this->core->getQueries()->addToQueue($validated_code, $user_id, $_POST['name'], $contact_info, $time_in);
        $this->sendSocketMessage(['type' => 'queue_update']);
        $this->core->addSuccessMessage("Added to queue");
        return new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']));
    }

    /**
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/office_hours_queue/togglePause", methods: ["POST"])]
    public function setQueuePauseState() {
        if (empty($_POST['pause_state'])) {
            $this->core->addErrorMessage("Missing queue position pause state");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $this->core->getQueries()->setQueuePauseState($_POST['pause_state'] === 'true');
        $this->core->addSuccessMessage($_POST['pause_state'] === 'true' ? "Position in queue paused" : "Position in queue unpaused");
        $this->sendSocketMessage(['type' => 'queue_update']);
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    /**
     * @return MultiResponse
     */
    #[AccessControl(role: "LIMITED_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/office_hours_queue/{queue_code}/restore", methods: ["POST"])]
    public function restorePerson($queue_code) {
        if (empty($_POST['entry_id'])) {
            $this->core->addErrorMessage("Missing entry ID");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if (empty($queue_code)) {
            $this->core->addErrorMessage("Missing queue name");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $this->core->getQueries()->restoreUserToQueue($_POST['entry_id']);
        $this->sendSocketMessage(['type' => 'queue_status_update']);
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    /**
     * @return MultiResponse
     */
    #[AccessControl(role: "LIMITED_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/office_hours_queue/{queue_code}/startHelp", methods: ["POST"])]
    public function startHelpPerson($queue_code) {
        if (empty($_POST['user_id'])) {
            $this->core->addErrorMessage("Missing user ID");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if (empty($queue_code)) {
            $this->core->addErrorMessage("Missing queue name");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $this->core->getQueries()->startHelpUser($_POST['user_id'], $queue_code);
        $this->sendSocketMessage(['type' => 'queue_status_update']);
        $this->core->addSuccessMessage("Started helping student");
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    /**
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/office_hours_queue/{queue_code}/finishHelp", methods: ["POST"])]
    public function finishHelpPerson($queue_code) {
        if (empty($_POST['user_id'])) {
            $this->core->addErrorMessage("Missing entry ID");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if (empty($queue_code)) {
            $this->core->addErrorMessage("Missing queue name");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if (!$this->core->getUser()->accessGrading() && $this->core->getUser()->getId() !== $_POST['user_id']) {
            $this->core->addErrorMessage("Permission denied to finish helping that person");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $remove_type = 'helped';
        if ($this->core->getUser()->getId() === $_POST['user_id']) {
            $remove_type = 'self_helped';//You helped yourself
        }


        $this->core->getQueries()->finishHelpUser($_POST['user_id'], $queue_code, $remove_type);
        $this->sendSocketMessage(['type' => 'full_update']);
        $this->core->addSuccessMessage("Finished helping student");
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    /**
     * @return MultiResponse
     */
    #[AccessControl(role: "LIMITED_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/office_hours_queue/{queue_code}/empty", methods: ["POST"])]
    public function emptyQueue($queue_code) {
        if (empty($queue_code)) {
            $this->core->addErrorMessage("Missing queue name");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        Logger::logQueueActivity($this->core->getConfig()->getTerm(), $this->core->getDisplayedCourseName(), $queue_code, "EMPTIED");
        $this->core->getQueries()->emptyQueue($queue_code);
        $this->core->addSuccessMessage("Queue emptied");
        $this->sendSocketMessage(['type' => 'full_update']);
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    /**
     * @return MultiResponse
     */
    #[AccessControl(role: "LIMITED_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/office_hours_queue/{queue_code}/toggle", methods: ["POST"])]
    public function toggleQueue($queue_code) {
        if (empty($queue_code)) {
            $this->core->addErrorMessage("Missing queue name");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }
        if (!isset($_POST['queue_state'])) {//Must be set as isset because empty(0) will return false even though 0 is a value
            $this->core->addErrorMessage("Missing queue state");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }
        Logger::logQueueActivity($this->core->getConfig()->getTerm(), $this->core->getDisplayedCourseName(), $queue_code, $_POST['queue_state'] === "1" ? 'CLOSED' : 'OPENED');
        $this->core->getQueries()->toggleQueue($queue_code, $_POST['queue_state']);
        $this->core->addSuccessMessage(($_POST['queue_state'] === "1" ? 'Closed' : 'Opened') . ' queue: "' . $queue_code . '"');
        $this->sendSocketMessage(['type' => 'toggle_queue']);

        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    /**
     * @return MultiResponse
     */
    #[AccessControl(role: "LIMITED_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/office_hours_queue/{queue_code}/deleteQueue", methods: ["POST"])]
    public function deleteQueue($queue_code) {
        if (empty($queue_code)) {
            $this->core->addErrorMessage("Missing queue name");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $this->core->getQueries()->deleteQueue($queue_code);
        $this->core->addSuccessMessage("Queue deleted");
        $this->sendSocketMessage(['type' => 'full_update']);
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }


    /**
     * @return MultiResponse
     */
    #[AccessControl(role: "LIMITED_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/office_hours_queue/{queue_code}/change_token", methods: ["POST"])]
    public function changeToken($queue_code) {
        if (empty($queue_code)) {
            $this->core->addErrorMessage("Missing queue name");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }


        //Replace whitespace with "_"
        $token = trim($_POST['token']);
        $re = '/^[\sa-zA-Z0-9_\-]*$/m';
        preg_match_all($re, $token, $matches_token, PREG_SET_ORDER, 0);
        if (count($matches_token) !== 1) {
            $this->core->addErrorMessage('Queue secret code must only contain letters, numbers, spaces, "_", and "-"');
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $queue_code = trim($queue_code);
        $this->core->getQueries()->changeQueueToken($token, $queue_code);
        $this->core->addSuccessMessage("Queue Access Code Changed");
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    /**
     * @return MultiResponse
     */
    #[AccessControl(role: "LIMITED_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/office_hours_queue/{queue_code}/change_regex", methods: ["POST"])]
    public function changeRegex($queue_code) {
        if (empty($queue_code)) {
            $this->core->addErrorMessage("Missing queue name");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $regex_pattern = trim($_POST['regex']);

        $queue_code = trim($_POST['code']);
        $this->core->getQueries()->changeQueueRegex($regex_pattern, $queue_code);
        $this->core->addSuccessMessage("Queue Regex Pattern Changed");
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    /**
     * @return RedirectResponse
     */
    #[AccessControl(role: "LIMITED_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/office_hours_queue/{queue_code}/change_contact_information", methods: ["POST"])]
    public function changeContactInformation($queue_code) {
        if (!isset($queue_code)) {
            $this->core->addErrorMessage("Missing queue name");
            return new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']));
        }

        $contact_information = $_POST['contact_information'] === "true";

        $queue_code = trim($_POST['code']);
        $this->core->getQueries()->changeQueueContactInformation($contact_information, $queue_code);
        $this->core->addSuccessMessage("Queue Contact Information Changed");
        return new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']));
    }
    /**
     * @return RedirectResponse
     */
    #[AccessControl(role: "LIMITED_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/office_hours_queue/send_queue_message", methods: ["POST"])]
    public function sendQueueMessage(): RedirectResponse {
        if (empty($_POST['code'])) {
            $this->core->addErrorMessage("Missing queue name");
            return new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']));
        }
        $code = trim($_POST['code']);

        //if we should clear the message instead of sending a new one
        if (!empty($_POST['clear_message'])) {
            $this->core->getQueries()->setQueueMessage($code, 'null');
            $this->sendSocketMessage(['type' => 'update_message', 'queue_code' => $code, 'alert' => false]);
            $this->core->addSuccessMessage("Message cleared");
        }
        else {
            if (empty($_POST['socket-message'])) {
                $this->core->addErrorMessage("Missing message");
                return new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']));
            }
            $message = trim($_POST['socket-message']);
            $this->core->getQueries()->setQueueMessage($code, $message);
            $this->sendSocketMessage(['type' => 'update_message', 'queue_code' => $code, 'alert' => true]);
            $this->core->addSuccessMessage("Message Sent To Queue");
        }

        return new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']));
    }

    #[Route("/courses/{_semester}/{_course}/office_hours_queue/get_queue_message", methods: ["GET"])]
    public function getQueueMessage() {
        if (!empty($_GET['code'])) {
            $row = $this->core->getQueries()->getQueueMessage(trim($_GET['code']));
            if ($row['message'] != null) {
                $results = $row['message'];
                $this->core->getOutput()->renderJsonSuccess($results);
            }
            else {
                return;
            }
        }
    }

    /**
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/office_hours_queue/current_queue", methods: ["GET"])]
    public function showCurrentQueue() {
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        return MultiResponse::webOnlyResponse(
            new WebResponse(
                'OfficeHoursQueue',
                'renderCurrentQueue',
                new OfficeHoursQueueModel($this->core),
                $this->core->getQueries()->getAllUsers()
            )
        );
    }


    /**
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/office_hours_queue/queue_history", methods: ["GET"])]
    public function showQueueHistory($full_history = false) {
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        return MultiResponse::webOnlyResponse(
            new WebResponse(
                'OfficeHoursQueue',
                'renderQueueHistory',
                new OfficeHoursQueueModel($this->core, $full_history),
                $this->core->getQueries()->getAllUsers()
            )
        );
    }

    /**
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/office_hours_queue/new_status", methods: ["GET"])]
    public function showNewStatus() {
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        return MultiResponse::webOnlyResponse(
            new WebResponse(
                'OfficeHoursQueue',
                'renderNewStatus',
                new OfficeHoursQueueModel($this->core),
                $this->core->getQueries()->getAllUsers()
            )
        );
    }

    /**
     * @return MultiResponse
     */
    #[AccessControl(role: "LIMITED_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/office_hours_queue/update_announcement", methods: ["POST"])]
    public function updateAnnouncement() {
        if (!isset($_POST['queue_announcement_message'])) {
            $this->core->addErrorMessage("Missing announcement content");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $config_json = $this->core->getConfig()->getCourseJson();
        $config_json['course_details']['queue_announcement_message'] = $_POST['queue_announcement_message'];
        if (!$this->core->getConfig()->saveCourseJson(['course_details' => $config_json['course_details']])) {
            return MultiResponse::JsonOnlyResponse(
                JsonResponse::getFailResponse('Could not save config file')
            );
        }
        $this->core->addSuccessMessage("Updated announcement");
        $this->sendSocketMessage(['type' => 'announcement_update']);
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    #[Route("/courses/{_semester}/{_course}/office_hours_queue/stats", methods: ["GET"])]
    public function showQueueStats() {
        $viewer = new OfficeHoursQueueModel($this->core);
        return new WebResponse(
            'OfficeHoursQueue',
            'showQueueStats',
            $viewer->getQueueDataOverall(),
            $viewer->getQueueDataToday(),
            $viewer->getQueueDataByWeekDayThisWeek(),
            $viewer->getQueueDataByWeekDay(),
            $viewer->getQueueDataByQueue(),
            $viewer->getQueueDataByWeekNumber()
        );
    }


    #[Route("/courses/{_semester}/{_course}/office_hours_queue/new_announcement", methods: ["GET"])]
    public function showNewAnnouncement() {
        if (!$this->core->getConfig()->isQueueEnabled()) {
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['home']))
            );
        }

        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        return MultiResponse::webOnlyResponse(
            new WebResponse(
                'OfficeHoursQueue',
                'renderNewAnnouncement',
                new OfficeHoursQueueModel($this->core),
                $this->core->getQueries()->getAllUsers()
            )
        );
    }

    #[AccessControl(role: "INSTRUCTOR")]
    #[Route("/courses/{_semester}/{_course}/office_hours_queue/student_stats", methods: ["GET"])]
    public function showQueueStudentStats() {
        if (!$this->core->getConfig()->isQueueEnabled()) {
            return new RedirectResponse($this->core->buildCourseUrl(['home']));
        }

        $viewer = new OfficeHoursQueueModel($this->core);
        return new WebResponse(
            'OfficeHoursQueue',
            'showQueueStudentStats',
            $viewer->getQueueDataStudent(),
            $this->core->getQueries()->getAllUsers()
        );
    }

    /**
     * this function opens a WebSocket client and sends a message with the corresponding update
     * @param array $msg_array
     */
    private function sendSocketMessage(array $msg_array): void {
        $msg_array['user_id'] = $this->core->getUser()->getId();
        $msg_array['page'] = $this->core->getConfig()->getTerm() . '-' . $this->core->getConfig()->getCourse() . "-office_hours_queue";
        try {
            $client = new Client($this->core);
            $client->json_send($msg_array);
        }
        catch (WebSocket\ConnectionException $e) {
            $this->core->addNoticeMessage("WebSocket Server is down, page won't load dynamically.");
        }
    }

    #[AccessControl(role: "INSTRUCTOR")]
    #[Route("/courses/{_semester}/{_course}/queue/student_search", methods: ["POST"])]
    public function studentSearch(): JsonResponse {
        $user_id = $_POST['student_id'];
        $user = $this->core->getQueries()->getUserById($user_id);
        if ($user === null) {
            $error = "Invalid Student ID";
            return JsonResponse::getFailResponse($error);
        }

        $result = $this->core->getQueries()->studentQueueSearch($user_id);
        $data = [];
        foreach ($result as $row) {
                $data[] = $row;
        }
        $responseData = json_encode($data);
        return JsonResponse::getSuccessResponse($responseData);
    }
}
