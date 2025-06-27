<?php

namespace app\controllers\student;

use app\controllers\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\response\RedirectResponse;
use app\libraries\routers\AccessControl;
use app\models\User;

class RainbowGradesController extends AbstractController {
    #[Route("/courses/{_semester}/{_course}/grades")]
    public function gradesReport() {
        if(!$this->core->getConfig()->displayRainbowGradesSummary() && $this->core->getUser()->getGroup() !== User::GROUP_INSTRUCTOR) {
            $this->core->addErrorMessage("Rainbow Grades are not enabled for this course");
            return new RedirectResponse($this->core->buildCourseUrl([]));
        }
        $grade_path = $this->core->getConfig()->getCoursePath() . "/reports/summary_html/"
            . $this->core->getUser()->getId() . "_summary.html";

        $grade_file = null;
        if (file_exists($grade_path)) {
            $grade_file = file_get_contents($grade_path);
        }

        $this->core->getOutput()->renderOutput(['submission', 'RainbowGrades'], 'showGrades', $grade_file);
    }

    /**
     * @AccessControl(role="INSTRUCTOR")
     * This route is used to view the grades of a specific student.
     **/
    #[Route("/courses/{_semester}/{_course}/users/view_grades", methods: ["POST"])]
    public function viewStudentGrades() {
        if (!isset($_POST["student_id"])) {
            $this->core->addErrorMessage("No student ID provided");
            return new RedirectResponse($this->core->buildCourseUrl(['users']));
        }
        $student_id = $_POST["student_id"];
        $user = $this->core->getQueries()->getUserById($student_id);
        if ($user === null) {
            $this->core->addErrorMessage("Invalid Student ID \"" . $_POST["student_id"] . "\"");
            return new RedirectResponse($this->core->buildCourseUrl(['users']));
        }

        $grade_path = $this->core->getConfig()->getCoursePath() . "/reports/summary_html/"
            . $user->getId() . "_summary.html";

        $grade_file = null;
        if (file_exists($grade_path)) {
            $grade_file = file_get_contents($grade_path);
        }

        $this->core->getOutput()->renderOutput(['submission', 'RainbowGrades'], 'showGrades', $grade_file);
    }
}
