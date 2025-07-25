<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\WebResponse;
use app\entities\email\EmailEntity;
use Symfony\Component\Routing\Annotation\Route;
use app\views\email\EmailStatusView;
use app\libraries\routers\AccessControl;
use app\repositories\email\EmailRepository;

class EmailStatusController extends AbstractController {
    public function __construct(Core $core) {
        parent::__construct($core);
    }

    /**
     * @return WebResponse
     */
    #[Route("/courses/{_semester}/{_course}/email_status", methods: ["GET"])]
    #[AccessControl(role: "INSTRUCTOR")]
    public function getEmailStatusPage(): WebResponse {
        $semester = $this->core->getConfig()->getTerm();
        $course = $this->core->getConfig()->getCourse();

        /** @var EmailRepository $repository */
        $repository = $this->core->getSubmittyEntityManager()->getRepository(EmailEntity::class);
        $num_page = $repository->getPageNum($semester, $course);

        return new WebResponse(
            EmailStatusView::class,
            'showEmailStatusPage',
            $num_page,
            $this->core->buildCourseUrl(["email_status_page"])
        );
    }

    /**
     * @return WebResponse
     */
    #[AccessControl(role: "INSTRUCTOR")]
    #[Route("/courses/{_semester}/{_course}/email_status_page", methods: ["GET"])]
    public function getEmailStatusesByPage(): WebResponse {
        $semester = $this->core->getConfig()->getTerm();
        $course = $this->core->getConfig()->getCourse();
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;

        /** @var EmailRepository $repository */
        $repository = $this->core->getSubmittyEntityManager()->getRepository(EmailEntity::class);
        $result = $repository->getEmailsByPage($page, $semester, $course);

        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        return new WebResponse(
            EmailStatusView::class,
            'renderStatusPage',
            $result
        );
    }

    /**
     * @return WebResponse
     */
    #[AccessControl(level: "SUPERUSER")]
    #[Route("/superuser/email_status", methods: ["GET"])]
    public function getSuperuserEmailStatusPage(): WebResponse {
        /** @var EmailRepository $repository */
        $repository = $this->core->getSubmittyEntityManager()->getRepository(EmailEntity::class);
        $num_page = $repository->getPageNum(null, null);

        return new WebResponse(
            EmailStatusView::class,
            'showEmailStatusPage',
            $num_page,
            $this->core->buildUrl(["superuser", "email_status_page"]),
            $this->core->buildUrl()
        );
    }

    /**
     * @return WebResponse
     */
    #[AccessControl(level: "SUPERUSER")]
    #[Route("/superuser/email_status_page", methods: ["GET"])]
    public function getSuperuserEmailStatusesByPage(): WebResponse {
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        /** @var EmailRepository $repository */
        $repository = $this->core->getSubmittyEntityManager()->getRepository(EmailEntity::class);
        $result = $repository->getEmailsByPage($page, null, null);
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        return new WebResponse(
            EmailStatusView::class,
            'renderStatusPage',
            $result
        );
    }
}
