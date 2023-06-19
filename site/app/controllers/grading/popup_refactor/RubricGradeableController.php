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
// Our super class
use app\controllers\AbstractController;

// Used for URL to function calls
use Symfony\Component\Routing\Annotation\Route;

// Used to compare gradeables
use app\libraries\GradeableType;

// Used for prev and next navigation, as well as outputing the sorting type as
// a string
use app\models\GradingOrder;


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

        $this->createBreadcrumbHeader();
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

        $this->current_student_id = $who_id;
        $this->sort_type = $sort;
        $this->sort_direction = $direction;
        $this->navigate_assigned_students_only;
    }


    /**
     * Sets $current_gradeable to the appropiate assignment unless $gradeable_id is invalid,
     * in which case an error is printed and the code exits.
     * 
     * @param string $gradeable_id - The id string of the current gradeable.
     */
    private function setCurrentGradeable($gradeable_id) {
        // tryGetGradeable inherited from AbstractController
        $this->current_gradeable = $this->tryGetGradeable($gradeable_id, false);

        // Gradeable must exist and be Rubric.
        $error_message = "";
        if ($this->current_gradeable === false)
            $error_message = 'Invalid Gradeable!';
        if (empty($error_message) && $this->current_gradeable->getType() !== GradeableType::ELECTRONIC_FILE) 
            $error_message = 'This gradeable is not a rubric gradeable.';

        if (!empty($error_message)) {
            $this->core->addErrorMessage($error_message);
            // The following line exits execution.
            $this->core->redirect($this->core->buildCourseUrl());
        }
    }


    /**
     * Created breadcrumb navigation header based on current sorting and gradeable id.
     * Navigation should be:
     *     Submitty > COURSE_NAME > GRADEABLE_NAME Grading > Grading Interface $sort_id $sort_direction Order
     */
    private function createBreadcrumbHeader() {
        $gradeableUrl = $this->core->buildCourseUrl(['gradeable', $this->current_gradeable->getId(),
            'grading', 'details']);
        $this->core->getOutput()->addBreadcrumb("{$this->current_gradeable->getTitle()} Grading", $gradeableUrl);

        $this->core->getOutput()->addBreadcrumb('Grading Interface ' .
            GradingOrder::getGradingOrderMessage($this->sort_type, $this->sort_direction));
    }

}

