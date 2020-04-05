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
                    'showPollsInstructor',
                    $this->core->getQueries()->getTodaysPolls(),
                    $this->core->getQueries()->getOlderPolls(),
                    $this->core->getQueries()->getFuturePolls()
                )
            );
        }
        else {
            return Response::WebOnlyResponse(
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
    * @Route("/{_semester}/{_course}/polls/viewPoll", methods={"POST"})
    * @return Response
    */
    public function showPoll() {
        $poll = $this->core->getQueries()->getPoll($_POST["poll_id"]);
        if ($this->core->getUser()->accessAdmin()) {
            return Response::WebOnlyResponse(
                new WebResponse(
                    'Poll',
                    'showPollInstructor',
                    $poll
                )
            );
        }
        else {
            return Response::WebOnlyResponse(
                new WebResponse(
                    'Poll',
                    'showPollStudent',
                    $poll
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
        $answers = array();
        $orders = array();
        for ($i = 0; $i < $response_count; $i++) {
            $responses[$_POST["option_id_" . $i]] = $_POST["response_" . $i];
            $orders[$_POST["option_id_" . $i]] = $_POST["order_" . $i];
            if (isset($_POST["is_correct_" . $i]) && $_POST["is_correct_" . $i] == "on") {
                $answers[] = $_POST["option_id_" . $i];
            }
        }
        $this->core->getQueries()->addNewPoll($_POST["name"], $_POST["question"], $responses, $answers, $_POST["release_date"], $orders);

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
        $this->core->getQueries()->openPoll($_POST["poll_id"]);

        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['polls']))
        );
    }

    /**
    * @Route("/{_semester}/{_course}/polls/setEnded", methods={"POST"})
    * @AccessControl(role="INSTRUCTOR")
    * @return Response
    */
    public function endPoll() {
        $this->core->getQueries()->endPoll($_POST["poll_id"]);

        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['polls']))
        );
    }

    /**
    * @Route("/{_semester}/{_course}/polls/submitResponse", methods={"POST"})
    * @return Response
    */
    public function submitResponse() {
        $poll = $this->core->getQueries()->getPoll($_POST["poll_id"]);
        if ($poll == null) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }
        if ($poll->isOpen()) {
            $this->core->getQueries()->submitResponse($_POST["poll_id"], $_POST["answer"]);
        }

        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['polls']))
        );
    }

    /**
     * @Route("/{_semester}/{_course}/polls/editPoll", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     * @return Response
     */
    public function editPoll() {
        $poll = $this->core->getQueries()->getPoll($_POST["poll_id"]);

        if ($poll == null) {
            $this->core->addErrorMessage("Invalid Poll ID");
            return Response::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['polls']))
            );
        }

        return Response::WebOnlyResponse(
            new WebResponse(
                'Poll',
                'editPoll',
                $poll
            )
        );
    }

    /**
     * @Route("/{_semester}/{_course}/polls/editPoll/submitEdits", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     * @return Response
     */
    public function submitEdits() {
        $response_count = $_POST["response_count"];
        $responses = array();
        $answers = array();
        $orders = array();
        for ($i = 0; $i < $response_count; $i++) {
            $responses[$_POST["option_id_" . $i]] = $_POST["response_" . $i];
            $orders[$_POST["option_id_" . $i]] = $_POST["order_" . $i];
            if (isset($_POST["is_correct_" . $i]) && $_POST["is_correct_" . $i] == "on") {
                $answers[] = $_POST["option_id_" . $i];
            }
        }
        $this->core->getQueries()->editPoll($_POST["poll_id"], $_POST["name"], $_POST["question"], $responses, $answers, $_POST["release_date"], $orders);
        
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['polls']))
        );
    }

    /**
     * @Route("/{_semester}/{_course}/polls/deletePoll", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     * @return Response
     */
    public function deletePoll() {
        $this->core->getQueries()->deletePoll($_POST["poll_id"]);
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['polls']))
        );
    }

    /**
     * @Route("/{_semester}/{_course}/polls/viewResults", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     * @return Response
     */
    public function viewResults() {
        $poll = $this->core->getQueries()->getPoll($_POST["poll_id"]);
        $results = $this->core->getQueries()->getResults($_POST["poll_id"]);
        //var_dump($results);
        return Response::WebOnlyResponse(
            new WebResponse(
                'Poll',
                'viewResults',
                $poll,
                $results
            )
        );
    }
}
