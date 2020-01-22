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
    * @param
    * @Route("/{_semester}/{_course}/office_hours_queue")
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
    * @param
    * @Route("/{_semester}/{_course}/office_hours_queue/open", methods={"POST"})
    * @AccessControl(role="LIMITED_ACCESS_GRADER")
    * @return Response
    */
    public function openQueue() {
        if (!isset($_POST['code']) || $_POST['code'] == "") {
            $this->core->addErrorMessage("No code was provided");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $queue_code = preg_replace('/\s+/', '_', $_POST['code']);

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
    * @param
    * @Route("/{_semester}/{_course}/office_hours_queue/add", methods={"POST"})
    * @return Response
    */
    public function addPerson() {
        if (!isset($_POST['code']) || !isset($_POST['name'])) {
            $this->core->addErrorMessage("Missing name or code in request");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $queue_code = preg_replace('/\s+/', '_', $_POST['code']);

        $validated_code = $this->core->getQueries()->isValidCode($queue_code);
        if (!$validated_code) {
            $this->core->addErrorMessage("invalid code");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if ($_POST['name'] == "") {
            $this->core->addErrorMessage("Invalid Name");
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
    * @param
    * @Route("/{_semester}/{_course}/office_hours_queue/remove", methods={"POST"})
    * @return Response
    */
    public function removePerson() {
        if (!isset($_POST['user_id'])) {
            $this->core->addErrorMessage("Missing user ID");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if (!$this->core->getUser()->accessGrading() && $this->core->getUser()->getId() != $_POST['user_id']) {
            $this->core->addErrorMessage("Permission denied to remove that person");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $remove_type = 3;//Mentor or ta removed you
        if ($this->core->getUser()->getId() == $_POST['user_id']) {
            $remove_type = 1;//You removed yourself
        }


        $this->core->getQueries()->removeUserFromQueue($_POST['user_id'], $remove_type, $_POST['queue_code']);
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    /**
    * @param
    * @Route("/{_semester}/{_course}/office_hours_queue/startHelp", methods={"POST"})
    * @AccessControl(role="LIMITED_ACCESS_GRADER")
    * @return Response
    */
    public function startHelpPerson() {
        if (!isset($_POST['user_id'])) {
            $this->core->addErrorMessage("Missing user ID");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }
        $this->core->getQueries()->startHelpUser($_POST['user_id'], $_POST['queue_code']);
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    /**
    * @param
    * @Route("/{_semester}/{_course}/office_hours_queue/finishHelp", methods={"POST"})
    * @return Response
    */
    public function finishHelpPerson() {
        if (!isset($_POST['user_id'])) {
            $this->core->addErrorMessage("Missing entry ID");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        if (!$this->core->getUser()->accessGrading() && $this->core->getUser()->getId() != $_POST['user_id']) {
            $this->core->addErrorMessage("Permission denied to finish helping that person");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $remove_type = 2;
        if ($this->core->getUser()->getId() == $_POST['user_id']) {
            $remove_type = 5;//You helped yourself
        }


        $this->core->getQueries()->finishHelpUser($_POST['user_id'], $_POST['queue_code'], $remove_type);
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    /**
    * @param
    * @Route("/{_semester}/{_course}/office_hours_queue/empty", methods={"POST"})
    * @AccessControl(role="LIMITED_ACCESS_GRADER")
    * @return Response
    */
    public function emptyQueue() {
        if (!isset($_POST['queue_code']) && $_POST['queue_code'] != "") {
            $this->core->addErrorMessage("Missing queue code");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $this->core->getQueries()->emptyQueue($_POST['queue_code']);
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    /**
    * @param
    * @Route("/{_semester}/{_course}/office_hours_queue/toggle", methods={"POST"})
    * @AccessControl(role="LIMITED_ACCESS_GRADER")
    * @return Response
    */
    public function toggleQueue() {
        if (!isset($_POST['queue_code']) && $_POST['queue_code'] != "") {
            $this->core->addErrorMessage("Missing queue code");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }
        if (!isset($_POST['queue_state']) && $_POST['queue_state'] != "") {
            $this->core->addErrorMessage("Missing queue state");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $this->core->getQueries()->toggleQueue($_POST['queue_code'], $_POST['queue_state']);
        if ($_POST['queue_state'] == 0) {
            $this->core->addSuccessMessage('Opened queue: "' . $_POST['queue_code'] . '"');
        }
        else {
            $this->core->addSuccessMessage('Closed queue: "' . $_POST['queue_code'] . '"');
        }

        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }

    /**
    * @param
    * @Route("/{_semester}/{_course}/office_hours_queue/deleteQueue", methods={"POST"})
    * @AccessControl(role="LIMITED_ACCESS_GRADER")
    * @return Response
    */
    public function deleteQueue() {
        if (!isset($_POST['queue_code'])) {
            $this->core->addErrorMessage("Missing queue code");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
            );
        }

        $this->core->getQueries()->deleteQueue($_POST['queue_code']);
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['office_hours_queue']))
        );
    }
}
