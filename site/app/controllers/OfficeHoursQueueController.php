<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\Response;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\models\OfficeHoursQueueViewer;
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
    public function showQueue() {
        if (!$this->core->getConfig()->isQueueEnabled()) {
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['home']))
            );
        }

        return Response::WebOnlyResponse(
            new WebResponse(
                'OfficeHoursQueue',                      //Goes to this file OfficeHoursQueueView.php
                'showTheQueue',                          //Runs this functin showTheQueue()
                new OfficeHoursQueueViewer($this->core)  //Passing in this variable which is a OfficeHoursQueueViewer object
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
            $this->core->addErrorMessage("No code was provided");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        //Replace whitespace with "_"
        $queue_code = preg_replace('/\s+/', '_', $_POST['code']);

        $re = '/^[a-zA-Z0-9_\-]+$/m';
        preg_match_all($re, $queue_code, $matches, PREG_SET_ORDER, 0);
        if (count($matches) !== 1) {
            $this->core->addErrorMessage('Queue Code must only contain letters, numbers, spaces, "_", and "-"');
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if ($this->core->getQueries()->openQueue($queue_code)) {
            $this->core->addSuccessMessage("New queue added");
        }
        else {
            $this->core->addErrorMessage("Unable to add queue. Make sure you have a unique queue code");
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
            $this->core->addErrorMessage("Missing name");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if (empty($queue_code)) {
            $this->core->addErrorMessage("Missing queue code");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $queue_code = preg_replace('/\s+/', '_', $queue_code);

        $validated_code = $this->core->getQueries()->isValidCode($queue_code);
        if (!$validated_code) {
            $this->core->addErrorMessage("invalid code");
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

        $this->core->getQueries()->addToQueue($validated_code, $this->core->getUser()->getId(), $_POST['name']);
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
            $this->core->addErrorMessage("Missing queue code");
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
            $this->core->addErrorMessage("Missing queue code");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $this->core->getQueries()->startHelpUser($_POST['user_id'], $queue_code);
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
            $this->core->addErrorMessage("Missing queue code");
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
            $this->core->addErrorMessage("Missing queue code");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $this->core->getQueries()->emptyQueue($queue_code);
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
            $this->core->addErrorMessage("Missing queue code");
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
            $this->core->addErrorMessage("Missing queue code");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $this->core->getQueries()->deleteQueue($queue_code);
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }
}
