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
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;

// Main Class:
class RubricGraderView extends AbstractView {
    // ---------------------------------

    // Member Variables:

    /**
     * @var Gradeable
     * The current gradeable being graded.
     */
    private $gradeable;

    /**
     * @var GradedGradeable
     * The current submission being graded.
     */
    private $current_submission;

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
     * This function is called in createMainRubricGraderPage of RubricGraderController.php.
     *
     * @param Gradeable $gradeable - The current gradeable.
     * @param GradedGradeable $current_submission - The current submission we are looking at.
     * @param string $sort_type - The current way we are sorting students. Used to create the header.
     * @param string $sort_direction -  Either "ASC" or "DESC" for ascending or descending sorting order.
     *     Used to create the header.
     * @param bool $is_peer_gradeable - True if the gradeable has peer grading.
     * @param bool $is_team_gradeable - True if the gradeable is a team gradeable.
     * @param string $blind_access_mode - Either "unblind", "single", or "double". See above for details.
     * @param string $details_url - URL of the details page for this Gradeable.
     *
     * @return string HTML for the RubricGrader page.
     *
     */
    public function createRubricGradeableView(
        Gradeable $gradeable,
        GradedGradeable $current_submission,
        string $sort_type,
        string $sort_direction,
        bool $is_peer_gradeable,
        bool $is_team_gradeable,
        string $blind_access_mode,
        string $details_url
    ): string {
        $this->setMemberVariables($gradeable, $current_submission, $is_peer_gradeable, $is_team_gradeable, $blind_access_mode);

        $this->createBreadcrumbHeader($sort_type, $sort_direction);

        $this->addCSSs();
        $this->addJavaScriptCode();

        $page_html = $this->core->getOutput()->renderTwigTemplate("grading/popup_refactor/RubricGraderTop.twig");

        $page_html .= $this->renderNavigationBar($details_url);

        $page_html .= $this->core->getOutput()->renderTwigTemplate("grading/popup_refactor/RubricGraderBottom.twig");

        return $page_html;
    }


    /**
     * Sets the corresponding memeber variables based on provided arguments.
     *
     * @param Gradeable $gradeable - The current gradeable.
     * @param GradedGradeable $current_submission - The current submission we are looking at.
     * @param bool $is_peer_gradeable - True if the gradeable has peer grading.
     * @param bool $is_team_gradeable - True if the gradeable is a team gradeable.
     * @param string $blind_access_mode - Either "unblind", "single", or "double". See above for details.
     *     for details.
     */
    private function setMemberVariables(
        Gradeable $gradeable,
        GradedGradeable $current_submission,
        bool $is_peer_gradeable,
        bool $is_team_gradeable,
        string $blind_access_mode
    ): void {
        $this->gradeable = $gradeable;
        $this->current_submission = $current_submission;
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
    private function createBreadcrumbHeader(string $sort_type, string $sort_direction): void {
        $gradeableUrl = $this->core->buildCourseUrl(['gradeable', $this->gradeable->getId(),
            'grading', 'details']);
        $this->core->getOutput()->addBreadcrumb("{$this->gradeable->getTitle()} Grading", $gradeableUrl);

        $this->core->getOutput()->addBreadcrumb('Grading Interface ' .
            GradingOrder::getGradingOrderMessage($sort_type, $sort_direction));
    }


    /**
     * Adds CSS files used for the Rubric Grader page.
     */
    private function addCSSs(): void {
        $this->core->getOutput()->addInternalCss('electronic.css');
    }


    /**
     * Adds JavaScript code used for the Rubric Grader page.
     */
    private function addJavaScriptCode(): void {
        $this->core->getOutput()->addInternalJs('ta-grading-rubric.js');
        $this->core->getOutput()->addInternalJs('ta-grading.js');
    }

    /**
     * Creates the NavigationBar used to traverse between students.
     * @param string $details_url - URL of the Details page for this gradeable.
     * @return string HTML for the NavigationBar.
     */
    private function renderNavigationBar(string $details_url): string {
        return $this->core->getOutput()->renderTwigTemplate("grading/popup_refactor/NavigationBar.twig", [
            "blind_access_mode" => $this->blind_access_mode,
            "is_team_gradeable" => $this->is_team_gradeable,
            "gradeable_submitter" => $this->current_submission->getSubmitter(),
            "details_url" => $details_url,
            "progress" => 65 // TODO actually make progress work
        ]);
    }
}
