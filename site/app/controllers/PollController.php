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
        return Response::WebOnlyResponse(
            new WebResponse(
                'Poll',
                'showPolls'
            )
        );
    }
}