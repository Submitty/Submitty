<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\models\email\EmailStatusPage;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\routers\AccessControl;

/**
 * @AccessControl(role="INSTRUCTOR")
 */
class EmailStatusController extends AbstractController {
    public function __construct(Core $core) {
        parent::__construct($core);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/email_status")
     * @return MultiResponse
     */
    public function getEmailStatusPage() {
        $course = $this->core->getConfig()->getCourse();
        $semester = $this->core->getConfig()->getSemester();
        $result = $this->core->getQueries()->getEmailStatusWithCourse($course, $semester);
        return MultiResponse::webOnlyResponse(
            new WebResponse(
                "app\\views\\email\EmailStatusView",
                'showEmailStatus',
                $result
            )
        );
    }
}
