<?php

namespace app\models\gradeable;

use app\entities\calendar\CalendarItem;
use app\libraries\Core;
use app\models\Button;
use app\models\User;
use app\views\NavigationView;

class GradeableUtils {
    const VCS_TYPE_NONE = -1;
    const VCS_TYPE_SUBMITTY_HOSTED = 0;
    const VCS_TYPE_SUBMITTY_HOSTED_URL = 1;
    const VCS_TYPE_PUBLIC_GITHUB = 2;
    const VCS_TYPE_PRIVATE_GITHUB = 3;
    const VCS_TYPE_SELF_HOSTED = 4;

    /**
     * Get the gradeables of a specified course.
     *
     * @return array<string, array<string, Gradeable>|array<string, GradedGradeable>|array<string, Button>>
     * @throws \Exception
     */
    public static function getGradeablesFromCourse(Core $core, string $semester, string $title, array &$calendar_messages, bool $global = true): array {
        /** @var array<string, Gradeable> $gradeables */
        $gradeables = [];
        /** @var Gradeable[] $visible_gradeables */
        $visible_gradeables = [];
        /** @var array<string, GradedGradeable> $graded_gradeables */
        $graded_gradeables = [];
        /** @var array<string, Button> $submit_btns */
        $submit_btns = [];

        if ($global) {
            // Load the database and configuration of a course
            $core->loadCourseConfig($semester, $title);
            $core->loadCourseDatabase();
        }

        $calendar_messages[$title] = $core->getCourseEntityManager()->getRepository(CalendarItem::class)->findAll();

        // Load all Gradeable objects of the current course
        foreach ($core->getQueries()->getGradeableConfigs(null) as $gradeable) {
            /** @var Gradeable $gradeable */
            $gradeables[serialize([$semester, $title, $gradeable->getId()])] = $gradeable;
            $visible_gradeables[] = $gradeable;
        }

        // Load all GradedGradable objects of the current course
        foreach ($core->getQueries()->getGradedGradeables($visible_gradeables, $core->getUser()->getId()) as $gg) {
            /** @var GradedGradeable $gg */
            $graded_gradeables[serialize([$semester, $title, $gg->getGradeableId()])] = $gg;
        }

        // Create submit buttons for all gradeables
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

        if ($global) {
            // Disconnect from the course database
            $core->getCourseDB()->disconnect();
        }

        return ["gradeables" => $gradeables, "graded_gradeables" => $graded_gradeables, "submit_btns" => $submit_btns];
    }

    /**
     * A static factory method to create an array that contains information for all
     * gradeables in all courses of a single user.
     * The method loads from the database of all courses and get all gradeables information.
     * Only load once unless the user refreshes the page.
     * NOTE: Calendar Messages is passed by reference in order to be changed in "getGradeablesFromCourse"
     *
     * @return array<string, array<string, Gradeable>|array<string, GradedGradeable>|array<string, Button>>
     * @throws \Exception if a Gradeable failed to load from the database
     */
    public static function getAllGradeableListFromUserId(Core $core, User $user, array $courses, array &$calendar_messages): array {
        $gradeables = [];
        $graded_gradeables = [];
        $submit_btns = [];

        // Load the gradeable information for each course
        foreach ($courses as $course) {
            $gradeables_of_course = self::getGradeablesFromCourse($core, $course->getTerm(), $course->getTitle(), $calendar_messages);
            $gradeables = array_merge($gradeables, $gradeables_of_course["gradeables"]);
            $graded_gradeables = array_merge($graded_gradeables, $gradeables_of_course["graded_gradeables"]);
            $submit_btns = array_merge($submit_btns, $gradeables_of_course["submit_btns"]);
        }

        $core->getConfig()->setCourseLoaded(false);
        return ["gradeables" => $gradeables, "graded_gradeables" => $graded_gradeables, "submit_btns" => $submit_btns];
    }

    /**
     * This function assumes that you are calling it where Core has a defined course
     *
     * @return \app\models\Button[][]|Gradeable[][]|GradedGradeable[][]|array[]
     */
    public static function getGradeablesFromUserAndCourse(Core $core, array &$calendar_messages): array {
        $gradeables_of_course = self::getGradeablesFromCourse(
            $core,
            $core->getConfig()->getTerm(),
            $core->getConfig()->getCourse(),
            $calendar_messages,
            false
        );

        $gradeables = $gradeables_of_course["gradeables"];
        $graded_gradeables = $gradeables_of_course["graded_gradeables"];
        $submit_btns = $gradeables_of_course["submit_btns"];

        return ["gradeables" => $gradeables,
            "graded_gradeables" => $graded_gradeables,
            "submit_btns" => $submit_btns];
    }
}
