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


# Main Class:
class RubricGradeableController extends AbstractController {

	# ---------------------------------

	# Member Variables:

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
     * @param string $gradeable_id - The id string of this gradeable, set in the gradeable's settings.
     * @param string $who_id - The id of the student we should grade.
     * @param string $sort - The current way we are sorting students. Determines who the next and prev students are.
     * @param string $direction - Either "ASC" or "DESC" for ascending or descending sorting order.
     * @param string $navigate_assigned_students_only - When going to the next student, this variable controls whether we skip students.
     * 
     */
	public function test_page_creation(
        $gradeable_id,
        $who_id = '',
        $sort = "id",
        $direction = "ASC",
        $navigate_assigned_students_only = "true"
    ) {

		# Set Member Variables
		$current_student_id = $who_id;
		

	}
}

