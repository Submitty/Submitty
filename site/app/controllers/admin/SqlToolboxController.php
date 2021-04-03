<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\response\JsonResponse;
use app\libraries\response\WebResponse;
use app\libraries\routers\AccessControl;
use app\views\admin\SqlToolboxView;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class UsersController
 * @package app\controllers\admin
 * @AccessControl(role="INSTRUCTOR")
 */

class SqlToolboxController extends AbstractController {
    /**
     * @Route("/courses/{_semester}/{_course}/sql_toolbox", methods={"GET"})
     */
    public function showToolbox(): WebResponse {
        return new WebResponse(SqlToolboxView::class, 'showToolbox');
    }

    /**
     * @Route("/courses/{_semester}/{_course}/sql_toolbox", methods={"POST"})
     */
    public function runQuery(): JsonResponse {
        $this->core->getCourseDB()->query($_POST['sql']);
        return JsonResponse::getSuccessResponse($this->core->getCourseDB()->rows());
    }
}
