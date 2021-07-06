<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\views\email\EmailStatusView;
use app\libraries\routers\AccessControl;

class EmailStatusController extends AbstractController {
    public function __construct(Core $core) {
        parent::__construct($core);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/email_status")
     * @AccessControl(role="INSTRUCTOR")
     * @return MultiResponse
     */
    public function getEmailStatusPage() {
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        $result = $this->core->getQueries()->getEmailStatusWithCourse($semester, $course);
        return MultiResponse::webOnlyResponse(
            new WebResponse(
                EmailStatusView::class,
                'showEmailStatus',
                $result
            )
        );
    }

    /**
     * @Route("/superuser/email_status")
     * @AccessControl(level="SUPERUSER")
     * @return WebResponse
     */
    public function getSuperuserEmailStatusPage(): WebResponse {
        $results = [];
        $emailStatuses = $this->core->getQueries()->getAllEmailStatuses();
        return new WebResponse(
            EmailStatusView::class,
            'showSuperuserEmailStatus',
            $emailStatuses
        );
    }
}
