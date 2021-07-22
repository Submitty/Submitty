<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\entities\email\EmailEntity;
use app\models\EmailStatusModel;
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
        $page = isset($_POST['page']) ? $_POST['page'] : 1;
        //$count = $this->core->getQueries()->getDistinctEmailSubject($semester, $course);
        //$result = $this->core->getQueries()->getEmailStatusWithCourse($semester, $course);
        $num_page = $this->core->getSubmittyEntityManager()->getRepository(EmailEntity::class)->getPageNum($semester, $course);
        $result = new EmailStatusModel($this->core, $this->core->getSubmittyEntityManager()->getRepository(EmailEntity::class)->getEmailsByPage($page, $semester, $course));
        
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
        $email_statuses = $this->core->getQueries()->getAllEmailStatuses();
        return new WebResponse(
            EmailStatusView::class,
            'showSuperuserEmailStatus',
            $email_statuses
        );
    }
}
