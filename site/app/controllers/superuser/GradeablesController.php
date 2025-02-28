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

class GradeablesController extends AbstractController {
    /**
     * @AccessControl(level="SUPERUSER")
     */
    #[Route("/api/superuser/gradeables")]
    #[Route("/superuser/gradeables")]
    public function viewGradeablesList(): MultiResponse {
        $this->core->getUser()->setGroup(\app\models\User::GROUP_INSTRUCTOR);
        /** @var array<string, \app\models\gradeable\Gradeable> */
        $gradeables = [];
        foreach ($this->core->getQueries()->getAllUnarchivedCourses() as $course) {
            /** @var \app\models\Course $course */
            $this->core->loadCourseConfig($course->getTerm(), $course->getTitle());
            $this->core->loadCourseDatabase();
            foreach ($this->core->getQueries()->getGradeableConfigs(null) as $gradeable) {
                /** @var \app\models\gradeable\Gradeable $gradeable */
                $gradeables["{$course->getTerm()}_{$course->getTitle()}_{$gradeable->getId()}"] = $gradeable;
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

    #[Route("/api/{term}/{course}/gradeables/list", methods: ['GET'])]
    public function viewUsersGradeableList(string $term, string $course): JsonResponse {
        $user_id = $_GET['user_id'] ?? '';
        if ($this->core->getUser()->getGroup() !== \app\models\User::GROUP_INSTRUCTOR && ($user_id !== $this->core->getUser()->getId())) {
            return JsonResponse::getFailResponse('API key and specified user_id are not for the same user.');
        }
        if (!$this->core->getQueries()->courseExists($term, $course)) {
            return JsonResponse::getFailResponse("Course $course for term $term does not exist");
        }
        $this->core->loadCourseConfig($term, $course);
        $this->core->loadCourseDatabase();
        $gradeables = new GradeableList($this->core, $this->core->getUser());
        return JsonResponse::getSuccessResponse($gradeables->toJson());
    }
}
