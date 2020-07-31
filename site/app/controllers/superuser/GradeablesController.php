<?php

declare(strict_types=1);

namespace app\controllers\superuser;

use app\controllers\AbstractController;
use app\libraries\routers\AccessControl;
use app\libraries\response\JsonResponse;
use app\libraries\response\WebResponse;
use app\libraries\response\MultiResponse;
use app\models\gradeable\GradeableList;
use app\views\superuser\GradeablesView;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @AccessControl(level="SUPERUSER")
 */
class GradeablesController extends AbstractController {
    /**
     * @Route("/api/superuser/gradeables")
     * @Route("/superuser/gradeables")
     * @AccessControl(level="SUPERUSER")
     */
    public function viewGradeablesList(): MultiResponse {
        $this->core->getUser()->setGroup(\app\models\User::GROUP_INSTRUCTOR);
        /** @var array<string, \app\models\gradeable\Gradeable> */
        $gradeables = [];
        foreach ($this->core->getQueries()->getAllUnarchivedCourses() as $course) {
            /** @var \app\models\Course $course */
            $this->core->loadCourseConfig($course->getSemester(), $course->getTitle());
            $this->core->loadCourseDatabase();
            foreach ($this->core->getQueries()->getGradeableConfigs(null) as $gradeable) {
                /** @var \app\models\gradeable\Gradeable $gradeable */
                $gradeables["{$course->getSemester()}_{$course->getTitle()}_{$gradeable->getId()}"] = $gradeable;
            }
            $this->core->getCourseDB()->disconnect();
        }
        $this->core->getConfig()->setCourseLoaded(false);
        $gradeable_list = new GradeableList($this->core, null, $gradeables);
        return new MultiResponse(
            JsonResponse::getSuccessResponse($gradeable_list->getGradeablesBySection()),
            new WebResponse(GradeablesView::class, 'showGradeablesList', $gradeable_list)
        );
    }
}
