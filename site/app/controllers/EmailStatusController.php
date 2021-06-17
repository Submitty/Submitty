<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\views\email\EmailStatusView;
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
     * @Route("email/check_announcement", methods={"GET"})
     * @return JsonResponse
     */
    public function checkAnnouncement(){
        if (isset($_GET["id"])) {
            $exists = $this->core->getQueries()->existsAnnouncementsId($_GET["id"]);
            return JsonResponse::getSuccessResponse([
                "exists" => $exists
            ]);
        }
        return JsonResponse::getFailResponse("No id provided");
    }
}
