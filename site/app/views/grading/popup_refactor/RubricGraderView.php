<?php

/**
 * ---------------------
 *
 * RubricGradearView.php
 *
 * This class creates the main display window for the Rubric Grader, featuring the
 * NavigationBar and the PanelBar.
 *
 * The function createRubricGradeableView is called from createMainRubricGraderPage
 * of RubricGraderController.php.
 *
 * ---------------------
 */

// Namespace:
namespace app\views\grading\popup_refactor;

// Includes:
use app\views\AbstractView;
use app\models\GradingOrder;

// Main Class:
class RubricGraderView extends AbstractView {
    // ---------------------------------

    // Member Variables:

    /**
     * The current gradeable being graded.
     */
    private $gradeable;

    /**
    * @var bool
    * True if the current gradeable has peer grading.
    */
    private $is_peer_gradeable;

     /**
    * @var bool
    * True if the current gradeable has teams.
    */
    private $is_team_gradeable;

    /**
     * @var string
     *
     * The access mode for the current user for this gradeable. 
     * Possible Values:
     *  - "unblind" - Nothing about students is hidden.
     *  - "single"  - For peer grading or for full access grading's Anonymous Mode. Graders cannot see
     *               who they are currently grading.
     *  - "double"  - For peer grading. In addition to blinded peer graders, students cannot
     *               see which peer they are currently grading.
     */
    private $blind_access_mode = "";

    // ---------------------------------


    // ---


    // ---------------------------------

    /**
     * Creates the Rubric Grading page visually.
     * This function is called in reateMainRubricGraderPage of RubricGraderController.php.
     *
     * @param string $gradeable - The current Gradeable.
     * @param string $sort_type - The current way we are sorting students. Used to create the header.
     * @param string $sort_direction -  Either "ASC" or "DESC" for ascending or descending sorting order.
     *     Used to create the header.
     * @param bool $is_peer_gradeable - True if the gradeable has peer grading.
     * @param bool $is_team_gradeable - True if the gradeable is a team gradeable.
     * @param string $blind_access_mode - Either "unblind", "single", or "double". See above for details.
     *
     */
    public function createRubricGradeableView(
        $gradeable,
        $sort_type,
        $sort_direction,
        $is_peer_gradeable,
        $is_team_gradeable,
        $blind_access_mode
    ) {
        $this->setMemberVariables($gradeable, $is_peer_gradeable, $is_team_gradeable, $blind_access_mode);

        $this->createBreadcrumbHeader($sort_type, $sort_direction);

        $this->addCSSs();

        $this->renderNavigationBar();
    }


    /**
     * Sets the corresponding memeber variables based on provided arguments.
     *
     * @param string $gradeable - The current Gradeable.
     * @param bool $is_peer_gradeable - True if the gradeable has peer grading.
     * @param bool $is_team_gradeable - True if the gradeable is a team gradeable.
     * @param string $blind_access_mode - Either "unblind", "single", or "double". See above for details.
     *     for details.
     */
    private function setMemberVariables($gradeable, $is_peer_gradeable, $is_team_gradeable, $blind_access_mode) {
        $this->gradeable = $gradeable;
        $this->$is_peer_gradeable = $is_peer_gradeable;
        $this->is_team_gradeable = $is_team_gradeable;
        $this->blind_access_mode = $blind_access_mode;
    }


    /**
     * Created breadcrumb navigation header based on current sorting and gradeable id.
     * Navigation should be:
     *     Submitty > COURSE_NAME > GRADEABLE_NAME Grading > Grading Interface $sort_id $sort_direction Order
     *
     * @param string $sort_type - The current way we are sorting students.
     * @param string $sort_direction -  Either "ASC" or "DESC" for ascending or descending sorting order.
     */
    private function createBreadcrumbHeader($sort_type, $sort_direction) {
        $gradeableUrl = $this->core->buildCourseUrl(['gradeable', $this->gradeable->getId(),
            'grading', 'details']);
        $this->core->getOutput()->addBreadcrumb("{$this->gradeable->getTitle()} Grading", $gradeableUrl);

        $this->core->getOutput()->addBreadcrumb('Grading Interface ' .
            GradingOrder::getGradingOrderMessage($sort_type, $sort_direction));
    }


    /**
     * Adds CSS files used for the Rubric Grader page.
     */
    private function addCSSs() {
        $this->core->getOutput()->addInternalCss('popup_refactor/navigation-bar.css');
    }


    /**
     * Creates the NavigationBar used to traverse between students.
     */
    private function renderNavigationBar() {

    }
}
