<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\Response;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\models\OfficeHoursQueueModel;
use app\libraries\routers\AccessControl;

/**
 * Class OfficeHoursQueueController
 *
 */
class OfficeHoursQueueController extends AbstractController {

    public function __construct(Core $core) {
        parent::__construct($core);
    }

    /**
    * @Route("/{_semester}/{_course}/office_hours_queue", methods={"GET"})
    * @return Response
    */
    public function showQueue($full_history = false) {
        if (!$this->core->getConfig()->isQueueEnabled()) {
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['home']))
            );
        }

        return Response::WebOnlyResponse(
            new WebResponse(
                'OfficeHoursQueue',
                'showTheQueue',
                new OfficeHoursQueueModel($this->core, $full_history)
            )
        );
    }

    /**
    * @Route("/{_semester}/{_course}/office_hours_queue", methods={"POST"})
    * @AccessControl(role="LIMITED_ACCESS_GRADER")
    * @return Response
    */
    public function openQueue() {
        if (empty($_POST['code'])) {
            $this->core->addErrorMessage("Missing queue name");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if (empty($_POST['token'])) {
            $this->core->addErrorMessage("Missing secret code");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        //Replace whitespace with "_"
        $queue_code = preg_replace('/\s+/', '_', trim($_POST['code']));
        $token = preg_replace('/\s+/', '_', trim($_POST['token']));

        $re = '/^[a-zA-Z0-9_\-]+$/m';
        preg_match_all($re, $queue_code, $matches_code, PREG_SET_ORDER, 0);
        preg_match_all($re, $token, $matches_token, PREG_SET_ORDER, 0);
        if (count($matches_code) !== 1 || count($matches_token) !== 1) {
            $this->core->addErrorMessage('Queue name and secret code must only contain letters, numbers, spaces, "_", and "-"');
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if ($this->core->getQueries()->openQueue($queue_code, $token)) {
            $this->core->addSuccessMessage("New queue added");
        }
        else {
            $this->core->addErrorMessage("Unable to add queue. Make sure you have a unique queue name");
        }

        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }


    /**
    * @Route("/{_semester}/{_course}/office_hours_queue/{queue_code}/add", methods={"POST"})
    * @return Response
    */
    public function addPerson($queue_code) {
        if (empty($_POST['name'])) {
            $this->core->addErrorMessage("Missing user's name");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if (empty($queue_code)) {
            $this->core->addErrorMessage("Missing queue name");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if (empty($_POST['token'])) {
            $this->core->addErrorMessage("Missing secret code");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $contact_info = null;
        if ($this->core->getConfig()->getQueueContactInfo()) {
            if (empty($_POST['contact_info'])) {
                $this->core->addErrorMessage("Missing contact info");
                return Response::RedirectOnlyResponse(
                    new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
                );
            }
            else {
                $contact_info = $_POST['contact_info'];
            }
        }

        $queue_code = preg_replace('/\s+/', '_', trim($queue_code));
        $token = preg_replace('/\s+/', '_', trim($_POST['token']));

        $validated_code = $this->core->getQueries()->isValidCode($queue_code, $token);
        if (!$validated_code) {
            $this->core->addErrorMessage("Invalid secret code");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if ($this->core->getQueries()->alreadyInAQueue()) {
            $this->core->addErrorMessage("You are already in the queue");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $this->core->getQueries()->addToQueue($validated_code, $this->core->getUser()->getId(), $_POST['name'], $contact_info);
        $this->core->addSuccessMessage("Added to queue");
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }


    /**
    * @Route("/{_semester}/{_course}/office_hours_queue/{queue_code}/remove", methods={"POST"})
    * @return Response
    */
    public function removePerson($queue_code) {
        if (empty($_POST['user_id'])) {
            $this->core->addErrorMessage("Missing user ID");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if (empty($queue_code)) {
            $this->core->addErrorMessage("Missing queue name");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if (!$this->core->getUser()->accessGrading() && $this->core->getUser()->getId() !== $_POST['user_id']) {
            $this->core->addErrorMessage("Permission denied to remove that person");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $remove_type = 'removed';//Mentor or ta removed you
        if ($this->core->getUser()->getId() === $_POST['user_id']) {
            $remove_type = 'self';//You removed yourself
        }


        $this->core->getQueries()->removeUserFromQueue($_POST['user_id'], $remove_type, $queue_code);
        $this->core->addSuccessMessage("Removed from queue");
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    /**
    * @Route("/{_semester}/{_course}/office_hours_queue/{queue_code}/restore", methods={"POST"})
    * @AccessControl(role="LIMITED_ACCESS_GRADER")
    * @return Response
    */
    public function restorePerson($queue_code) {
        if (empty($_POST['entry_id'])) {
            $this->core->addErrorMessage("Missing entry ID");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if (empty($queue_code)) {
            $this->core->addErrorMessage("Missing queue name");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $this->core->getQueries()->restoreUserToQueue($_POST['entry_id']);
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    /**
    * @Route("/{_semester}/{_course}/office_hours_queue/{queue_code}/startHelp", methods={"POST"})
    * @AccessControl(role="LIMITED_ACCESS_GRADER")
    * @return Response
    */
    public function startHelpPerson($queue_code) {
        if (empty($_POST['user_id'])) {
            $this->core->addErrorMessage("Missing user ID");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if (empty($queue_code)) {
            $this->core->addErrorMessage("Missing queue name");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $this->core->getQueries()->startHelpUser($_POST['user_id'], $queue_code);
        $this->core->addSuccessMessage("Started helping student");
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    /**
    * @Route("/{_semester}/{_course}/office_hours_queue/{queue_code}/finishHelp", methods={"POST"})
    * @return Response
    */
    public function finishHelpPerson($queue_code) {
        if (empty($_POST['user_id'])) {
            $this->core->addErrorMessage("Missing entry ID");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if (empty($queue_code)) {
            $this->core->addErrorMessage("Missing queue name");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if (!$this->core->getUser()->accessGrading() && $this->core->getUser()->getId() !== $_POST['user_id']) {
            $this->core->addErrorMessage("Permission denied to finish helping that person");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $remove_type = 'helped';
        if ($this->core->getUser()->getId() === $_POST['user_id']) {
            $remove_type = 'self_helped';//You helped yourself
        }


        $this->core->getQueries()->finishHelpUser($_POST['user_id'], $queue_code, $remove_type);
        $this->core->addSuccessMessage("Finished helping student");
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    /**
    * @Route("/{_semester}/{_course}/office_hours_queue/{queue_code}/empty", methods={"POST"})
    * @AccessControl(role="LIMITED_ACCESS_GRADER")
    * @return Response
    */
    public function emptyQueue($queue_code) {
        if (empty($queue_code)) {
            $this->core->addErrorMessage("Missing queue name");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $this->core->getQueries()->emptyQueue($queue_code);
        $this->core->addSuccessMessage("Queue emptied");
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    /**
    * @Route("/{_semester}/{_course}/office_hours_queue/{queue_code}/toggle", methods={"POST"})
    * @AccessControl(role="LIMITED_ACCESS_GRADER")
    * @return Response
    */
    public function toggleQueue($queue_code) {
        if (empty($queue_code)) {
            $this->core->addErrorMessage("Missing queue name");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }
        if (!isset($_POST['queue_state'])) {//Must be set as isset because empty(0) will return false even though 0 is a value
            $this->core->addErrorMessage("Missing queue state");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $this->core->getQueries()->toggleQueue($queue_code, $_POST['queue_state']);
        $this->core->addSuccessMessage(($_POST['queue_state'] === "1" ? 'Closed' : 'Opened') . ' queue: "' . $queue_code . '"');

        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    /**
    * @Route("/{_semester}/{_course}/office_hours_queue/{queue_code}/deleteQueue", methods={"POST"})
    * @AccessControl(role="LIMITED_ACCESS_GRADER")
    * @return Response
    */
    public function deleteQueue($queue_code) {
        if (empty($queue_code)) {
            $this->core->addErrorMessage("Missing queue name");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $this->core->getQueries()->deleteQueue($queue_code);
        $this->core->addSuccessMessage("Queue deleted");
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    /**
    * @Route("/{_semester}/{_course}/office_hours_queue/check_updates", methods={"GET"})
    * @return Response
    */
    public function checkUpdates() {
        return Response::JsonOnlyResponse(
            JsonResponse::getSuccessResponse($this->core->getQueries()->getLastQueueUpdate())
        );
    }

    /**
    * @Route("/{_semester}/{_course}/office_hours_queue/{queue_code}/change_token", methods={"POST"})
    * @AccessControl(role="LIMITED_ACCESS_GRADER")
    * @return Response
    */
    public function changeToken($queue_code) {
        if (empty($queue_code)) {
            $this->core->addErrorMessage("Missing queue name");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }


        //Replace whitespace with "_"
        $token = preg_replace('/\s+/', '_', trim($_POST['token']));
        $re = '/^[a-zA-Z0-9_\-]+$/m';
        preg_match_all($re, $token, $matches_token, PREG_SET_ORDER, 0);
        if (count($matches_token) !== 1) {
            $this->core->addErrorMessage('Queue secret code must only contain letters, numbers, spaces, "_", and "-"');
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $queue_code = preg_replace('/\s+/', '_', trim($queue_code));
        $this->core->getQueries()->changeQueueToken($token, $queue_code);
        $this->core->addSuccessMessage("Queue Code Changed");
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    /**
    * @Route("/{_semester}/{_course}/office_hours_queue/current_queue", methods={"GET"})
    * @return Response
    */
    public function showCurrentQueue() {
        if (!$this->core->getConfig()->isQueueEnabled()) {
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['home']))
            );
        }

        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        return Response::WebOnlyResponse(
            new WebResponse(
                'OfficeHoursQueue',
                'renderCurrentQueue',
                new OfficeHoursQueueModel($this->core)
            )
        );
    }
}
