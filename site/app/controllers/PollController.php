<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\Response;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\routers\AccessControl;

class PollController extends AbstractController {
    public function __construct(Core $core) {
        parent::__construct($core);
    }

    /**
    * @Route("/{_semester}/{_course}/polls", methods={"GET"})
    * @return Response
    */
    public function showPollsPage() {
        if ($this->core->getUser()->accessAdmin()) {
            return Response::WebOnlyResponse(
                new WebResponse(
                    'Poll',
                    'showPollsInstructor'
                )
            );
        }
        else {
            return Response::WebOnlyResponse(
                new WebResponse(
                    'Poll',
                    'showPollsStudent'
                )
            );
        }
        
    }

    /**
    * @Route("/{_semester}/{_course}/polls/newPoll", methods={"GET"})
    * @AccessControl(role="INSTRUCTOR")
    * @return Response
    */
    public function showNewPollPage() {
        return Response::WebOnlyResponse(
            new WebResponse(
                'Poll',
                'showNewPollPage'
            )
        );
    }


    /**
    * @Route("/{_semester}/{_course}/polls/newPoll", methods={"POST"})
    * @AccessControl(role="INSTRUCTOR")
    * @return Response
    */
    public function addNewPoll() {
        $response_count = $_POST["response_count"];
        $responses = array();
        for ($i = 0; $i < $response_count; $i++) {
            $responses[$i] = $_POST["response_" . $i];
        }
        $ans_index = (int)$_POST["answer"];
        var_dump($ans_index);
        $answer = $responses[$ans_index];
        $this->core->getQueries()->addNewPoll($_POST["name"], $_POST["question"], $responses, $responses[$ans_index]);

        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['polls']))
        );
    }

    /**
    * @Route("/{_semester}/{_course}/polls/setOpen", methods={"POST"})
    * @AccessControl(role="INSTRUCTOR")
    * @return Response
    */
    public function openPoll() {
        $this->core->getQueries()->setPollOpen($_POST["poll_id"], $_POST["open"]);

        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['polls']))
        );
    }
}