<?php

/**
 * ---------------------
 *
 * RubricGraderController.php
 * First Created By: Nia Heermance
 *
 * This class's createMainRubricGradeablePage will eventually be called when a
 * Rubric Gradeable's grading page is opened.
 *
 * Currently, to access the page associated with this class, enter URL:
 *
 *     /courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading_beta[?more_stuff_here_is_okay]
 *
 * This class is also responsible for updating popup windows created.
 *
 * ---------------------
 */

# Namespace:
namespace app\controllers\grading\popup_refactor;

# Includes:
use app\controllers\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\GradeableType;
use app\models\gradeable\Gradeable;

# Main Class:
class RubricGraderController extends AbstractController {
    # ---------------------------------

    # Member Variables:

    /**
     * @var Gradeable
     * The current gradeable being graded.
     */
    private $gradeable;

    /**
     * @var string
     * By what ordering are we sorting by.
     * Controls where next and prev arrows go.
     */
    private $sort_type;

    /**
     * @var string
     * For a given ordering, do we sort it ascending "ASC" or descending "DSC".
     * Controls where next and prev arrows go.
     */
    private $sort_direction;

    # ---------------------------------


    # ---


    # ---------------------------------

    # Functions:

    /**
     * Creates the Rubric Grading page.
     * @Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading_beta/grade")
     *
     * @param string $gradeable_id - The id string of the current gradeable.
     * @param string $who_id - The id of the student we should grade.
     * @param string $sort - The current way we are sorting students. Determines who the next and prev students are.
     * @param string $direction - Either "ASC" or "DESC" for ascending or descending sorting order.
     * @param string $navigate_assigned_students_only - When going to the next student, this variable controls
     *                whether we skip students.
     *
     * This page is loaded on line 476 of Details.twig when the Grade button is clicked.
     *
     * Note that the argument names cannot be changed easily as they need to line up with the arguments
     * provided to the URL.
     *
     */
    public function createMainRubricGraderPage(
        string $gradeable_id,
        string $who_id = '',
        string $sort = "id",
        string $direction = "ASC",
        string $navigate_assigned_students_only = "true"
    ) {

        $this->setMemberVariables($gradeable_id, $who_id, $sort, $direction, $navigate_assigned_students_only);

        $this->core->getOutput()->renderOutput(
            # Path:
            ['grading', 'popup_refactor', 'RubricGrader'],
            # Function Name:
            'createRubricGradeableView',
            # Arguments:
            $this->gradeable,
            $this->sort_type,
            $this->sort_direction
        );
    }


    /**
     * Sets the corresponding memeber variables based on provided arguments.
     *
     * @param string $gradeable_id - The id string of the current gradeable.
     * @param string $who_id - The id of the student we should grade.
     * @param string $sort - The current way we are sorting students. Determines who the next and prev students are.
     * @param string $direction - Either "ASC" or "DESC" for ascending or descending sorting order.
     * @param string $navigate_assigned_students_only - When going to the next student, this variable controls
     *     whether we skip students.
     */
    private function setMemberVariables(
        string $gradeable_id,
        string $who_id,
        string $sort,
        string $direction,
        string $navigate_assigned_students_only
    ) {
        $this->setCurrentGradeable($gradeable_id);

        $this->sort_type = $sort;
        $this->sort_direction = $direction;
    }


    /**
     * Sets $gradeable to the appropiate assignment unless $gradeable_id is invalid,
     * in which case an error is printed and the code exits.
     *
     * @param string $gradeable_id - The id string of the current gradeable.
     */
    private function setCurrentGradeable(string $gradeable_id) {
        // tryGetGradeable inherited from AbstractController
        $this->gradeable = $this->tryGetGradeable($gradeable_id, false);

        // Gradeable must exist and be Rubric.
        $error_message = "";
        if ($this->gradeable === false) {
            $error_message = 'Invalid Gradeable!';
        }
        elseif ($this->gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $error_message = 'This gradeable is not a rubric gradeable.';
        }

        if (!empty($error_message)) {
            $this->core->addErrorMessage($error_message);
            // The following line exits execution.
            $this->core->redirect($this->core->buildCourseUrl());
        }
    }
}
