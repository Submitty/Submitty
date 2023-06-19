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
 *     /courses/{_semester}/{_course}/gradeable/{gradeable_id}/new_grading_beta
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
	# <None Currently>

	# ---------------------------------


	# ---


	# ---------------------------------

	# Functions:

	/**
     * INFORMATION
     * 
     * @Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/new_grading_beta", methods={"GET"})
     * @param string $gradeable_id - The id string of this gradeable, set in the gradeable's settings.
     */
	public function test_page_creation($gradeable_id) {
		print "Hello World!";
	}
}

