<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\entities\email\EmailEntity;
use Symfony\Component\Routing\Annotation\Route;
use app\views\email\EmailStatusView;
use app\libraries\routers\AccessControl;

class EmailStatusController extends AbstractController {
    public function __construct(Core $core) {
        parent::__construct($core);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/email_status", methods={"GET"})
     * @AccessControl(role="INSTRUCTOR")
     * @return WebResponse
     */
    public function getEmailStatusPage() {
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();

        $num_page = $this->core->getSubmittyEntityManager()->getRepository(EmailEntity::class)->getPageNum($semester, $course);

        return new WebResponse(
            EmailStatusView::class,
            'showEmailStatusPage',
            $num_page,
            $this->core->buildCourseUrl(["email_status_page"]),
            $this->core->buildUrl()
        );
    }

    /**
     * @Route("/courses/{_semester}/{_course}/email_status_page", methods={"GET"})
     * @Route("/api/courses/{_semester}/{_course}/email_status_page", methods={"GET"})
     * @AccessControl(role="INSTRUCTOR")
     * @return array
     */
    public function getEmailStatusesByPage(): array {
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        $page = isset($_POST['page']) ? $_POST['page'] : 1;
        $result = $this->core->getSubmittyEntityManager()->getRepository(EmailEntity::class)->getEmailsByPage($page, $semester, $course);

        return $this->core->getOutput()->renderJsonSuccess(
            $this->core->getOutput()->renderTemplate(
                EmailStatusView::class,
                'renderStatusPage',
                $result
            )
        );
    }

    /**
     * @Route("/superuser/email_status", methods={"GET"})
     * @AccessControl(level="SUPERUSER")
     * @return WebResponse
     */
    public function getSuperuserEmailStatusPage(): WebResponse {
        $num_page = $this->core->getSubmittyEntityManager()->getRepository(EmailEntity::class)->getPageNum();

        return new WebResponse(
            EmailStatusView::class,
            'showEmailStatusPage',
            $num_page,
            $this->core->buildUrl(["superuser", "email_status_page"]),
            $this->core->buildUrl()
        );
    }

    /**
     * @Route("/superuser/email_status_page", methods={"GET"})
     * @AccessControl(level="SUPERUSER")
     * @return WebResponse
     */
    public function getSuperuserEmailStatusesByPage(): WebResponse {
        $page = $_GET['page'] ?? 1;
        $result = $this->core->getSubmittyEntityManager()->getRepository(EmailEntity::class)->getEmailsByPage($page);
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        return new WebResponse(
            EmailStatusView::class,
            'renderStatusPage',
            $result
        );
    }
}
