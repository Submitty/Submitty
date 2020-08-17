<?php

namespace app\controllers;

use app\exceptions\DatabaseException;
use app\libraries\Core;
use app\libraries\ExceptionHandler;
use app\libraries\GradeableType;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\Submitter;
use app\models\gradeable\GradeableList;
use Symfony\Component\Routing\Annotation\Route;

class NavigationController extends AbstractController {
    public function __construct(Core $core) {
        parent::__construct($core);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/no_access")
     */
    public function noAccess() {
        $this->core->getOutput()->renderOutput('Error', 'noAccessCourse');
    }

    /**
     * @Route("/courses/{_semester}/{_course}", requirements={"_semester": "^(?!api)[^\/]+", "_course": "[^\/]+"})
     */
    public function navigationPage() {
        try {
            $gradeables_list = new GradeableList($this->core);
        }
        catch (DatabaseException $e) {
            ExceptionHandler::handleException($e);

            $error_messages = ['A broken gradeable was detected when collecting gradeable information from the database.  Contact the system administrator for assistance.'];
            return $this->core->getOutput()->renderOutput('Error', 'genericError', $error_messages);
        }

        $future_gradeables_list = $gradeables_list->getFutureGradeables();
        $beta_gradeables_list = $gradeables_list->getBetaGradeables();
        $open_gradeables_list = $gradeables_list->getOpenGradeables();
        $closed_gradeables_list = $gradeables_list->getClosedGradeables();
        $grading_gradeables_list = $gradeables_list->getGradingGradeables();
        $graded_gradeables_list = $gradeables_list->getGradedGradeables();

        $sections_to_lists = [];

        $user = $this->core->getUser();

        if ($user->accessGrading()) {
            $sections_to_lists[GradeableList::FUTURE] = $future_gradeables_list;
            $sections_to_lists[GradeableList::BETA] = $beta_gradeables_list;
        }

        $sections_to_lists[GradeableList::OPEN] = $open_gradeables_list;
        $sections_to_lists[GradeableList::CLOSED] = $closed_gradeables_list;
        $sections_to_lists[GradeableList::GRADING] = $grading_gradeables_list;
        $sections_to_lists[GradeableList::GRADED] = $graded_gradeables_list;

        //Remove gradeables we are not allowed to view
        foreach ($sections_to_lists as $key => $value) {
            $sections_to_lists[$key] = array_filter($value, [$this, "filterCanView"]);
        }

        //Clear empty sections
        foreach ($sections_to_lists as $key => $value) {
            // if there are no gradeables, don't show this category
            if (count($sections_to_lists[$key]) == 0) {
                unset($sections_to_lists[$key]);
            }
        }

        // Get a single array of the visible gradeables
        $visible_gradeables = [];
        $submit_everyone = [];
        foreach ($sections_to_lists as $gradeables) {
            foreach ($gradeables as $gradeable) {
                $visible_gradeables[] = $gradeable;
                $submit_everyone[$gradeable->getId()] =
                    $this->core->getAccess()->canI('gradeable.submit.everyone', ['gradeable' => $gradeable]);
            }
        }

        // Get the user data for each gradeable
        $graded_gradeables = [];
        if (count($visible_gradeables) !== 0) {
            foreach ($this->core->getQueries()->getGradedGradeables($visible_gradeables, $user->getId()) as $gg) {
                $graded_gradeables[$gg->getGradeableId()] = $gg;
            }
        }

        $gradeable_ids_and_titles = $this->core->getQueries()->getAllGradeablesIdsAndTitles();

        $this->core->getOutput()->renderOutput('Navigation', 'showGradeables', $sections_to_lists, $graded_gradeables, $submit_everyone, $gradeable_ids_and_titles);
        $this->core->getOutput()->renderOutput('Navigation', 'deleteGradeableForm');
        $this->core->getOutput()->renderOutput('Navigation', 'closeSubmissionsWarning');
    }

    /**
     * Test if the current user is allowed to view this gradeable
     * @param Gradeable $gradeable
     * @return bool True if they are
     */
    private function filterCanView(Gradeable $gradeable) {
        $user = $this->core->getUser();

        //Remove incomplete gradeables for non-instructors
        if (
            !$user->accessAdmin()
            && $gradeable->getType() == GradeableType::ELECTRONIC_FILE
            && !$gradeable->hasAutogradingConfig()
        ) {
            return false;
        }

        // student users should only see electronic gradeables -- NOTE: for now, we might change this design later
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE && !$user->accessGrading()) {
            return false;
        }

        // if student view false, never show
        if (!$gradeable->isStudentView() && !$user->accessGrading()) {
            return false;
        }

        // if student view is true and they can only view after grades are released, filter appropriately
        if ($gradeable->isStudentView() && $gradeable->isStudentViewAfterGrades() && !$user->accessGrading()) {
            return $gradeable->isTaGradeReleased();
        }

        //If we're not instructor and this is not open to TAs
        $date = $this->core->getDateTimeNow();
        if ($gradeable->getTaViewStartDate() > $date && !$user->accessAdmin()) {
            return false;
        }

        return true;
    }
}
