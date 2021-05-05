<?php

namespace app\models\gradeable;

use app\libraries\Core;
use app\models\Button;
use app\models\Course;
use app\models\User;
use app\views\NavigationView;

class GradeableUtils {
    /**
     * Get the gradeables of a specified course.
     *
     * @param Core $core
     * @param Course $course
     * @return array<string, Gradeable[]|GradedGradeable[]|Button[]>
     * @throws \Exception
     */
    public static function getGradeablesFromCourse(Core $core, Course $course): array {
        /** @var array<string, Gradeable> $gradeables */
        $gradeables = [];
        /** @var Gradeable[] $visible_gradeables */
        $visible_gradeables = [];
        /** @var array<string, GradedGradeable> $graded_gradeables */
        $graded_gradeables = [];
        /** @var array<string, Button> $submit_btns */
        $submit_btns = [];

        $core->loadCourseConfig($course->getSemester(), $course->getTitle());
        $core->loadCourseDatabase();
        foreach ($core->getQueries()->getGradeableConfigs(null) as $gradeable) {
            /** @var Gradeable $gradeable */
            $gradeables[serialize([$course->getSemester(), $course->getTitle(), $gradeable->getId()])] = $gradeable;
            $visible_gradeables[] = $gradeable;
        }

        foreach ($core->getQueries()->getGradedGradeables($visible_gradeables, $core->getUser()->getId()) as $gg) {
            /** @var GradedGradeable $gg */
            $graded_gradeables[serialize([$course->getSemester(), $course->getTitle(), $gg->getGradeableId()])] = $gg;
        }

        foreach ($gradeables as $key => $gradeable) {
            $can_submit_everyone = $core->getAccess()->canI('gradeable.submit.everyone', ['gradeable' => $gradeable]);
            $graded_gradeable = array_key_exists($key, $graded_gradeables) ? $graded_gradeables[$key] : null;
            $section = GradeableList::getGradeableSection($core, $gradeable);
            if ($section === -1) {
                $submit_btns[$key] = null;
            }
            else {
                $submit_btns[$key] = NavigationView::getSubmitButton($core, $gradeable, $graded_gradeable, $section, $can_submit_everyone);
            }
        }

        $core->getCourseDB()->disconnect();

        return ["gradeables" => $gradeables, "graded_gradeables" => $graded_gradeables, "submit_btns" => $submit_btns];
    }

    /**
     * A static factory method to create a new GradeableList object that contains
     * all gradeables in all courses of a single user.
     * The method loads from the database of all courses and get all gradeables information.
     * Only load once unless the user refreshes the page.
     *
     * @param Core $core
     * @param User $user The user to filter gradeables by
     * @return array<string, Gradeable[]|GradedGradeable[]|Button[]>
     * @throws \Exception if a Gradeable failed to load from the database
     */
    public static function getAllGradeableListFromUserId(Core $core, User $user): array {
        $gradeables = [];
        $graded_gradeables = [];
        $submit_btns = [];

        // Load the gradeable information for each course
        $courses = $core->getQueries()->getCourseForUserId($user->getId());
        foreach ($courses as $course) {
            /** @var Course $course */
            $gradeables_of_course = self::getGradeablesFromCourse($core, $course);
            $gradeables = array_merge($gradeables, $gradeables_of_course["gradeables"]);
            $graded_gradeables = array_merge($graded_gradeables, $gradeables_of_course["graded_gradeables"]);
            $submit_btns = array_merge($submit_btns, $gradeables_of_course["submit_btns"]);
        }

        $core->getConfig()->setCourseLoaded(false);
        return ["gradeables" => $gradeables, "graded_gradeables" => $graded_gradeables, "submit_btns" => $submit_btns];
    }
}
