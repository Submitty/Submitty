<?php
/**
 * ---------------------
 * 
 * RubricGradeableController.php
 * First Created By: Nia Heermance
 * 
 * This class's functionNAME will eventually be called when a Rubric Gradeable's grading page is opened.
 * Currently, to access the page associated with this class, enter URL:
 * 
 *     /courses/{_semester}/{_course}/gradeable/{gradeable_id}/new_grading_beta[?more_stuff_here_is_okay]
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


# Main Class:
class RubricGradeableController extends AbstractController {

    # ---------------------------------

    # Member Variables:

    /**
     * The current gradeable being graded.
     */
    private $current_gradeable;

    /**
     * @var current_student_id
     * The anonomous id of the student currently being grade.
     * This id can be set with setCurrentStudentId or when loading this page's URL
     * with ?who_id=INSERT_ID.
     */
    private $current_student_id;

    /**
     * @var sort_type
     * By what ordering are we sorting by.
     * Controls where next and prev arrows go.
     */
    private $sort_type;

    /**
     * @var sort_direction
     * For a given ordering, do we sort it ascending "ASC" or descending "DSC".
     * Controls where next and prev arrows go.
     */
    private $sort_direction;

    /**
     * @var navigate_assigned_students_only
     * Do we skip students that we are not assigned to when pressing next or prev arrows?
     */
    private $navigate_assigned_students_only;

    # ---------------------------------


    # ---


    # ---------------------------------

    # Functions:

    /**
     * Displays the Rubric Grading page. 
     * @Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/new_grading_beta/grade")
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
    public function createMainRubricGradeablePage($gradeable_id, $who_id = '', $sort = "id", $direction = "ASC",
            $navigate_assigned_students_only = "true") {
        $this->setMemberVariables($gradeable_id, $who_id, $sort, $direction, $navigate_assigned_students_only);
    }


    /**
     * Sets the corresponding memeber variables based on provided arguments.
     * 
     * @param string $gradeable_id - The id string of the current gradeable.
     * @param string $who_id - The id of the student we should grade.
     * @param string $sort - The current way we are sorting students. Determines who the next and prev students are.
     * @param string $direction - Either "ASC" or "DESC" for ascending or descending sorting order.
     * @param string $navigate_assigned_students_only - When going to the next student, this variable controls whether we skip students. 
     */
    private function setMemberVariables($gradeable_id, $who_id, $sort, $direction, $navigate_assigned_students_only) {
        $this->setCurrentGradeable($gradeable_id);

        $current_student_id = $who_id;
        $sort_type = $sort;
        $sort_direction = $direction;
        $navigate_assigned_students_only;
    }


    /**
     * Sets $current_gradeable to the appropiate assignment unless $gradeable_id is invalid,
     * in which case an error is printed and the code exits.
     * 
     * @param string $gradeable_id - The id string of the current gradeable.
     */
    private function setCurrentGradeable($gradeable_id) {
        // tryGetGradeable inherited from AbstractController
        $gradeable = $this->tryGetGradeable($gradeable_id, false);

        // Gradeable must exist and be Rubric.
        $error_message = "";
        if ($gradeable === false)
            $error_message = 'Invalid Gradeable!';
        if (empty($error_message) && $gradeable->getType() !== GradeableType::ELECTRONIC_FILE) 
            $error_message = 'This gradeable is not a rubric gradeable.';

        if (!empty($error_message)) {
            $this->core->addErrorMessage($error_message);
            // The following line exits execution.
            $this->core->redirect($this->core->buildCourseUrl());
        }
    }

}

