<?php
namespace app\views;
use app\models\Button;
use \app\libraries\GradeableType;
use app\models\gradeable\AutoGradedGradeable;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\TaGradedGradeable;
use app\models\gradeable\GradeableList;
use app\libraries\FileUtils;

class NavigationView extends AbstractView {

    const gradeableSections = [
        GradeableList::FUTURE => [
            //What title is displayed to the user for each category
            "title" => "FUTURE",
            //Shown italicized after the title
            "subtitle" => "visible only to Instructors",
            //Element id of the header row (used primarily by e2e tests)
            "section_id" => "future",
            //What bootstrap button the student button will be. Information about bootstrap buttons can be found here:
            //https://www.w3schools.com/bootstrap/bootstrap_buttons.asp
            "button_type_submission" => "btn-default",
            //What bootstrap button the instructor/TA button will be
            "button_type_grading" => "btn-default",
            //The general text of the button under the category
            //It is general since the text could change depending if the user submitted something or not and other factors.
            "prefix" => "ALPHA SUBMIT"
        ],
        GradeableList::BETA => [
            "title" => "BETA",
            "subtitle" => "open for testing by TAs",
            "section_id" => "beta",
            "button_type_submission" => "btn-default",
            "button_type_grading" => "btn-default",
            "prefix" => "BETA SUBMIT"
        ],
        GradeableList::OPEN => [
            "title" => "OPEN",
            "subtitle" => "",
            "section_id" => "open",
            "button_type_submission" => "btn-primary",
            "button_type_grading" => "btn-default",
            "prefix" => "SUBMIT"
        ],
        GradeableList::CLOSED => [
            "title" => "PAST DUE",
            "subtitle" => "",
            "section_id" => "closed",
            "button_type_submission" => "btn-danger",
            "button_type_grading" => "btn-default",
            "prefix" => "LATE SUBMIT"
        ],
        GradeableList::GRADING => [
            "title" => "CLOSED",
            "subtitle" => "being graded by TA/Instructor",
            "section_id" => "items_being_graded",
            "button_type_submission" => "btn-default",
            "button_type_grading" => "btn-primary",
            "prefix" => "VIEW SUBMISSION"
        ],
        GradeableList::GRADED => [
            "title" => "GRADES AVAILABLE",
            "subtitle" => "",
            "section_id" => "graded",
            "button_type_submission" => 'btn-default',
            "button_type_grading" => 'btn-default',
            "prefix" => "VIEW GRADE"
        ]
    ];

    const DATE_FORMAT = "m/d/Y @ H:i";

    public function noAccessCourse() {
        return $this->core->getOutput()->renderTwigTemplate("error/NoAccessCourse.twig", [
            "course_name" => $this->core->getDisplayedCourseName()
        ]);
    }

    public function showGradeables($sections_to_list, $graded_gradeables, array $submit_everyone) {
        // ======================================================================================
        // DISPLAY CUSTOM BANNER (typically used for exam seating assignments)
        // note: placement of this information this may eventually be re-designed
        // ======================================================================================
        $message_file_path = $this->core->getConfig()->getCoursePath() . "/reports/summary_html/" . $this->core->getUser()->getId() . "_message.html";
        $message_file_contents = "";
        if (file_exists($message_file_path)) {
            $message_file_contents = file_get_contents($message_file_path);
        }
        $display_custom_message = $this->core->getConfig()->displayCustomMessage();

        /* @var Button[] $top_buttons */
        $top_buttons = [];

        // ======================================================================================
        // COURSE MATERIALS BUTTON -- visible to everyone
        // ======================================================================================
        $course_path = $this->core->getConfig()->getCoursePath();
        $course_materials_path = $course_path."/uploads/course_materials";
        $any_files = FileUtils::getAllFiles($course_materials_path);
        if ($this->core->getUser()->getGroup()=== 1 || !empty($any_files)) {
            $top_buttons[] = new Button($this->core, [
                "href" => $this->core->buildUrl(array('component' => 'grading', 'page' => 'course_materials', 'action' => 'view_course_materials_page')),
                "title" => "Course Materials",
                "class" => "btn btn-primary"
            ]);
        }

	      // ======================================================================================
        // IMAGES BUTTON -- visible to limited access graders and up
        // ======================================================================================
        $images_course_path = $this->core->getConfig()->getCoursePath();
        $images_path = Fileutils::joinPaths($images_course_path,"uploads/student_images");
        $any_images_files = FileUtils::getAllFiles($images_path, array(), true);
        if ($this->core->getUser()->getGroup()=== 1 && count($any_images_files)===0) {
            $top_buttons[] = new Button($this->core, [
                "href" => $this->core->buildUrl(array('component' => 'grading', 'page' => 'images', 'action' => 'view_images_page')),
                "title" => "Upload Student Photos",
                "class" => "btn btn-primary"
            ]);
        }
        else if (count($any_images_files)!==0 && $this->core->getUser()->accessGrading()) {
            $sections = $this->core->getUser()->getGradingRegistrationSections();
            if (!empty($sections) || $this->core->getUser()->getGroup() !== 3) {
                $top_buttons[] = new Button($this->core, [
                    "href" => $this->core->buildUrl(array('component' => 'grading', 'page' => 'images', 'action' => 'view_images_page')),
                    "title" => "View Student Photos",
                    "class" => "btn btn-primary"
                ]);
            }
        }

        // ======================================================================================
        // CREATE NEW GRADEABLE BUTTON -- only visible to instructors
        // ======================================================================================
        if ($this->core->getUser()->accessAdmin()) {
            $top_buttons[] = new Button($this->core, [
                "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'view_gradeable_page')),
                "title" => "New Gradeable",
                "class" => "btn btn-primary"
            ]);
            $top_buttons[] = new Button($this->core, [
                "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'gradeable', 'action' => 'upload_config')),
                "title" => "Upload Config",
                "class" => "btn btn-primary"
            ]);

        }
        // ======================================================================================
        // LATE DAYS TABLE BUTTON
        // ======================================================================================

        $top_buttons[] = new Button($this->core, [
            "href" => $this->core->buildUrl(array('component' => 'student', 'page' => 'view_late_table')),
            "title" => "Show my late days information",
            "class" => "btn btn-primary"
        ]);
        // ======================================================================================
        // FORUM BUTTON
        // ======================================================================================

        if ($this->core->getConfig()->isForumEnabled()) {
            $top_buttons[] = new Button($this->core, [
                "href" => $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')),
                "title" => "Discussion Forum",
                "class" => "btn btn-primary"
            ]);
        }
        // ======================================================================================
        // GRADES SUMMARY BUTTON
        // ======================================================================================
        $display_rainbow_grades_summary = $this->core->getConfig()->displayRainbowGradesSummary();
        if ($display_rainbow_grades_summary) {
            $top_buttons[] = new Button($this->core, [
                "href" => $this->core->buildUrl(array('component' => 'student', 'page' => 'rainbow')),
                "title" => "View Grades",
                "class" => "btn btn-primary"
            ]);
        }

        // ======================================================================================
        // INDEX OF ALL GRADEABLES
        // ======================================================================================

        $render_sections = [];
        foreach ($sections_to_list as $list_section => $gradeable_list) {
            /** @var Gradeable[] $gradeable_list */

            $render_gradeables = [];
            foreach ($gradeable_list as $gradeable_id => $gradeable) {
                /** @var Gradeable $gradeable */

                $graded_gradeable = $graded_gradeables[$gradeable->getId()] ?? null;
                $render_gradeables[] = [
                    "id" => $gradeable->getId(),
                    "name" => $gradeable->getTitle(),
                    "url" => $gradeable->getInstructionsUrl(),
                    "can_delete" => $this->core->getUser()->accessAdmin() && $gradeable->canDelete(),
                    "buttons" => $this->getButtons($gradeable, $graded_gradeable, $list_section, $submit_everyone[$gradeable->getId()]),
                    "has_build_error" => $gradeable->anyBuildErrors()
                ];
            }

            //Copy
            $render_section = self::gradeableSections[$list_section];
            $render_section["gradeables"] = $render_gradeables;

            $render_sections[] = $render_section;
        }
        return $this->core->getOutput()->renderTwigTemplate("Navigation.twig", [
            "top_buttons" => $top_buttons,
            "sections" => $render_sections,
            "message_file_contents" => $message_file_contents,
            "display_custom_message" => $display_custom_message
        ]);
    }

    /**
     * Get the list of buttons to display to the user for a Gradeable
     * @param Gradeable $gradeable
     * @param GradedGradeable|null $graded_gradeable The graded gradeble instance, or null if no data yet
     * @param int $list_section
     * @param bool $submit_everyone If the user can submit for another user
     * @return array
     */
    private function getButtons(Gradeable $gradeable, $graded_gradeable, int $list_section, bool $submit_everyone): array {
        $buttons = [];
        $buttons[] = $this->hasTeamButton($gradeable) ? $this->getTeamButton($gradeable, $graded_gradeable) : null;
        $buttons[] = $this->hasSubmitButton($gradeable) ? $this->getSubmitButton($gradeable, $graded_gradeable, $list_section, $submit_everyone) : null;

        if ($this->hasGradeButton($gradeable)) {
            $buttons[] = $this->getGradeButton($gradeable, $list_section);
        }

        //Admin buttons
        if ($this->core->getUser()->accessAdmin()) {
            $buttons[] = $this->hasEditButton() ? $this->getEditButton($gradeable) : null;
            $buttons[] = $this->hasQuickLinkButton() ? $this->getQuickLinkButton($gradeable, $list_section) : null;
        }

        return $buttons;
    }

    //Tests for if we have the various buttons

    /**
     * @param Gradeable $gradeable
     * @return bool
     */
    private function hasTeamButton(Gradeable $gradeable): bool {
        return $gradeable->isTeamAssignment();
    }

    /**
     * @param Gradeable $gradeable
     * @return bool
     */
    private function hasSubmitButton(Gradeable $gradeable): bool {
        return $gradeable->getType() === GradeableType::ELECTRONIC_FILE;
    }

    /**
     * @param Gradeable $gradeable
     * @return bool
     */
    private function hasGradeButton(Gradeable $gradeable): bool {
        // full access graders & instructors are allowed to view submissions of assignments with no manual grading
        $im_allowed_to_view_submissions = $this->core->getUser()->accessGrading() && !$gradeable->isTaGrading() && $this->core->getUser()->getGroup() <= 2;

        // limited access graders and full access graders can preview/view the grading interface only if they are allowed by the min grading group
        $im_a_grader = $this->core->getUser()->accessGrading() && $this->core->getUser()->getGroup() <= $gradeable->getMinGradingGroup();

        // students can only view the submissions & grading interface if its a peer grading assignment
        $im_a_peer_grader = $this->core->getUser()->getGroup() === 4 && $gradeable->isPeerGrading();

        // TODO: look through this logic and put into new access system
        return $im_a_peer_grader || $im_a_grader || $im_allowed_to_view_submissions;
    }

    /**
     * @return bool
     */
    private function hasEditButton(): bool {
        return $this->core->getUser()->accessAdmin();
    }


    /**
     * @return bool
     */
    private function hasQuickLinkButton(): bool {
        return $this->core->getUser()->accessAdmin();
    }

    /**
     * @param Gradeable $gradeable
     * @param GradedGradeable|null $graded_gradeable
     * @return Button|null
     */
    private function getTeamButton(Gradeable $gradeable, $graded_gradeable) {
        // Team management button, only visible on team assignments
        $date = new \DateTime("now", $this->core->getConfig()->getTimezone());
        $past_lock_date = $date < $gradeable->getTeamLockDate();

        if ($past_lock_date) {
            $team_display_date = "(teams lock {$gradeable->getTeamLockDate()->format(self::DATE_FORMAT)})";
        } else {
            $team_display_date = '';
        }

        if ($graded_gradeable === null || $graded_gradeable->getSubmitter()->getTeam() === null) {
            if ($past_lock_date) {
                $team_button_type = 'btn-primary';
            } else {
                $team_button_type = 'btn-danger';
            }
            $team_button_text = 'CREATE TEAM';
            $teams = $this->core->getQueries()->getTeamsByGradeableId($gradeable->getId());
            foreach ($teams as $t) {
                if ($t->sentInvite($this->core->getUser()->getId())) {
                    $team_button_text = 'CREATE/JOIN TEAM';
                    break;
                }
            }
        } else {
            if ($past_lock_date) {
                $team_button_type = 'btn-primary';
                $team_button_text = 'MANAGE TEAM';
            } else {
                $team_button_type = 'btn-default';
                $team_button_text = 'VIEW TEAM';
            }
        }

        $button = new Button($this->core, [
            "title" => $team_button_text,
            "subtitle" => $team_display_date,
            "href" => $this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId(), 'page' => 'team')),
            "class" => "btn {$team_button_type} btn-nav"
        ]);

        return $button;
    }

    /**
     * @param Gradeable $gradeable
     * @param GradedGradeable|null $graded_gradeable
     * @param int $list_section
     * @param bool $submit_everyone If the user can submit for another user
     * @return Button|null
     */
    private function getSubmitButton(Gradeable $gradeable, $graded_gradeable, int $list_section, bool $submit_everyone) {
        $class = self::gradeableSections[$list_section]["button_type_submission"];
        $title = self::gradeableSections[$list_section]["prefix"];
        $display_date = ($list_section == GradeableList::FUTURE || $list_section == GradeableList::BETA) ?
            "(opens " . $gradeable->getSubmissionOpenDate()->format(self::DATE_FORMAT) . ")" :
            "(due " . $gradeable->getSubmissionDueDate()->format(self::DATE_FORMAT) . ")";
        $points_percent = NAN;

        $href = $this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId()));
        $progress = null;
        $disabled = false;

        //Button types that override any other buttons
        if (!$gradeable->hasAutogradingConfig()) {
            $button = new Button($this->core, [
                "title" => "Need to run BUILD_{$this->core->getConfig()->getCourse()}.sh",
                "disabled" => true,
                "class" => "btn btn-default btn-nav"
            ]);

            return $button;
        }

        if($graded_gradeable !== null) {
            /** @var TaGradedGradeable $ta_graded_gradeable */
            $ta_graded_gradeable = $graded_gradeable->getTaGradedGradeable();
            /** @var AutoGradedGradeable $auto_graded_gradeable */
            $auto_graded_gradeable = $graded_gradeable->getAutoGradedGradeable();

            //calculate the point percentage
            if($auto_graded_gradeable !== null) {
                $points_percent = $auto_graded_gradeable->getNonHiddenPercent(true);
            }


            //If the button is autograded and has been submitted once, give a progress bar.
            if (!is_nan($points_percent) &&  $graded_gradeable->getAutoGradedGradeable()->isAutoGradingComplete() &&
                ($list_section == GradeableList::CLOSED || $list_section == GradeableList::OPEN)) {
                $progress = $points_percent * 100;
            }

            // Not submitted or cancelled, after submission deadline
            if (!$graded_gradeable->getAutoGradedGradeable()->isAutoGradingComplete() &&
                ($list_section == GradeableList::GRADED || $list_section == GradeableList::GRADING)) {
                //You forgot to submit
                $class = "btn-danger";
            }

            // TA grading enabled, the gradeable is fully graded, and the user hasn't viewed it
            if ($gradeable->isTaGrading() && $graded_gradeable->isTaGradingComplete() &&
                $ta_graded_gradeable->getUserViewedDate() === null &&
                $list_section === GradeableList::GRADED) {
                //Graded and you haven't seen it yet
                $class = "btn-success";
            }
            // Submitted, currently after grade released date
            if ($graded_gradeable->getAutoGradedGradeable()->isAutoGradingComplete() &&
                $list_section == GradeableList::GRADED) {
                if ($gradeable->isTaGrading()) {
                    if (!$graded_gradeable->isTaGradingComplete()) {
                        // Incomplete TA grading
                        $class = "btn-default";
                    }
                } else {
                    // No TA grading
                    $class = "btn-default";
                }
            }

            if ($graded_gradeable->getAutoGradedGradeable()->isAutoGradingComplete() &&
                $gradeable->getAutogradingConfig()->getTotalNonHiddenNonExtraCredit() != 0 && $points_percent >= 0.5 &&
                $list_section == GradeableList::CLOSED) {
                $class = "btn-default";
            }

            if ($graded_gradeable->getAutoGradedGradeable()->isAutoGradingComplete() &&
                ($list_section == GradeableList::GRADED || $list_section == GradeableList::GRADING)) {
                $display_date = "";
            }

            if ($graded_gradeable->getAutoGradedGradeable()->isAutoGradingComplete() && $list_section == GradeableList::OPEN) {
                //if the user submitted something on time
                $title = "RESUBMIT";
            } else if ($graded_gradeable->getAutoGradedGradeable()->isAutoGradingComplete() && $list_section == GradeableList::CLOSED) {
                //if the user submitted something past time
                $title = "LATE RESUBMIT";
            } else if (!$graded_gradeable->getAutoGradedGradeable()->isAutoGradingComplete() && ($list_section == GradeableList::GRADED || $list_section == GradeableList::GRADING)) {
                //to change the text to overdue submission if nothing was submitted on time
                $title = "OVERDUE SUBMISSION";
            } else if ($gradeable->isTaGrading() && !$graded_gradeable->isTaGradingComplete() && $list_section == GradeableList::GRADED) {
                //when there is no TA grade and due date passed
                $title = "TA GRADE NOT AVAILABLE";
            }
        } else {
            // This means either the user isn't on a team
            if ($gradeable->isTeamAssignment()) {
                // team assignment, no team
                if (!$submit_everyone) {
                    $title = "MUST BE ON A TEAM TO SUBMIT";
                    $disabled = true;
                }
                if ($list_section > GradeableList::OPEN) {
                    $class = "btn-danger";
                    if ($submit_everyone) {
                        // team assignment, no team
                        $title = "OVERDUE SUBMISSION";
                        $disabled = false;
                    }
                }
            }
        }

        $button = new Button($this->core, [
            "title" => $title,
            "subtitle" => $display_date,
            "href" => $href,
            "progress" => $progress,
            "disabled" => $disabled,
            "class" => "btn {$class} btn-nav btn-nav-submit"
        ]);

        return $button;
    }

    /**
     * @param Gradeable $gradeable
     * @param int $list_section
     * @return Button|null
     */
    private function getGradeButton(Gradeable $gradeable, int $list_section) {
        //Location, location never changes
        if($this->core->getUser()->accessAdmin()){
            $view="all";
        }
        else{
            $view=null;
        }
        if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
            $href = $this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'gradeable_id' => $gradeable->getId()));
        } else if ($gradeable->getType() === GradeableType::CHECKPOINTS) {
            $href = $this->core->buildUrl(array('component' => 'grading', 'page' => 'simple', 'action' => 'lab', 'g_id' => $gradeable->getId(), 'view' => $view));
        } else if ($gradeable->getType() === GradeableType::NUMERIC_TEXT) {
            $href = $this->core->buildUrl(array('component' => 'grading', 'page' => 'simple', 'action' => 'numeric', 'g_id' => $gradeable->getId(), 'view' => $view));
        } else {
            //Unknown type of gradeable
            $href = "";
        }

        //Default values
        $class = self::gradeableSections[$list_section]["button_type_grading"];
        $date_text = null;
        $progress = null;

        //Button types that override any other buttons
        if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
            if (!$gradeable->hasAutogradingConfig()) {
                $button = new Button($this->core, [
                    "title" => "Need to run BUILD_{$this->core->getConfig()->getCourse()}.sh",
                    "disabled" => true,
                    "class" => "btn btn-default btn-nav"
                ]);

                return $button;
            }

            if ($this->core->getQueries()->getNumberRegradeRequests($gradeable->getId()) !== 0) {
                //Open regrade requests
                $button = new Button($this->core, [
                    "title" => "REGRADE",
                    "class" => "btn btn-danger btn-nav btn-nav-grade",
                    "href" => $href
                ]);

                return $button;
            }
        }

        if ($list_section === GradeableList::GRADING || $list_section === GradeableList::GRADED) {
            if ($list_section === GradeableList::GRADING) {
                $title = 'GRADE';
                $date_text = '(grades due ' . $gradeable->getGradeReleasedDate()->format(self::DATE_FORMAT) . ')';
            } else {
                $title = 'REGRADE';
            }

            if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
                if ($gradeable->isTaGrading()) {
                    $TA_percent = $gradeable->getGradingProgress($this->core->getUser());

                    if ($TA_percent === 1) {
                        //If they're done, change the text to REGRADE
                        $class = 'btn-default';
                        $title = 'REGRADE';
                    } else {
                        if (!is_nan($TA_percent) && $list_section === GradeableList::GRADED) {
                            //You forgot somebody
                            $class = 'btn-danger';
                            $title = 'GRADE';
                        }
                    }

                    //Give the TAs a progress bar too
                    if (!is_nan($TA_percent)) {
                        $progress = $TA_percent * 100;
                    }
                } else {
                    $title = "VIEW SUBMISSIONS";
                }
            } else {
                //Labs & Tests don't have exciting buttons
                $class = 'btn-default';
            }
        } else {
            if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE && !$gradeable->isTaGrading()) {
                $title = "VIEW SUBMISSIONS";
                $date_text = "(no manual grading)";
            } else {
                //Before grading has opened, only thing we can do is preview
                $title = 'PREVIEW GRADING';
                $date_text = '(grading opens ' . $gradeable->getGradeStartDate()->format(self::DATE_FORMAT) . ")";
            }
        }

        $button = new Button($this->core, [
            "title" => $title,
            "subtitle" => $date_text,
            "href" => $href,
            "progress" => $progress,
            "class" => "btn btn-nav btn-nav-grade {$class}",
        ]);

        return $button;
    }

    /**
     * @param Gradeable $gradeable
     * @return Button|null
     */
    private function getEditButton(Gradeable $gradeable) {
        $button = new Button($this->core, [
            "title" => "Edit",
            "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'edit_gradeable_page', 'id' => $gradeable->getId())),
            "class" => "fa fa-pencil",
            "title_on_hover" => true,
            "aria_label" => "edit gradeable {$gradeable->getId()}"
        ]);
        return $button;
    }

    /**
     * @param Gradeable $gradeable
     * @param int $list_section
     * @return Button|null
     */
    private function getQuickLinkButton(Gradeable $gradeable, int $list_section) {
        $button = null;
        if ($list_section === GradeableList::GRADING) {
            $button = new Button($this->core, [
                "subtitle" => "RELEASE \nGRADES NOW",
                "href" => $this->core->buildUrl([
                    'component' => 'admin',
                    'page' => 'admin_gradeable',
                    'action' => 'quick_link',
                    'id' => $gradeable->getId(),
                    'quick_link_action' => 'release_grades_now']),
                "class" => "btn btn-primary btn-nav btn-nav-open"
            ]);
        } else if ($list_section === GradeableList::FUTURE) {
            $button = new Button($this->core, [
                "subtitle" => "OPEN TO \nTAS NOW",
                "href" => $this->core->buildUrl([
                    'component' => 'admin',
                    'page' => 'admin_gradeable',
                    'action' => 'quick_link',
                    'id' => $gradeable->getId(),
                    'quick_link_action' => 'open_ta_now']),
                "class" => "btn btn-primary btn-nav btn-nav-open"
            ]);
        } else if ($list_section === GradeableList::BETA) {
            if ($gradeable->getType() == GradeableType::ELECTRONIC_FILE) {
                $button = new Button($this->core, [
                    "subtitle" => "OPEN NOW",
                    "href" => $this->core->buildUrl([
                        'component' => 'admin',
                        'page' => 'admin_gradeable',
                        'action' => 'quick_link',
                        'id' => $gradeable->getId(),
                        'quick_link_action' => 'open_students_now']),
                    "class" => "btn btn-primary btn-nav btn-nav-open"
                ]);
            } else {
                $button = new Button($this->core, [
                    "subtitle" => "OPEN TO \nGRADING NOW",
                    "href" => $this->core->buildUrl([
                        'component' => 'admin',
                        'page' => 'admin_gradeable',
                        'action' => 'quick_link',
                        'id' => $gradeable->getId(),
                        'quick_link_action' => 'open_grading_now']),
                    "class" => "btn btn-primary btn-nav btn-nav-open"
                ]);
            }
        } else if ($list_section === GradeableList::CLOSED) {
            $button = new Button($this->core, [
                "subtitle" => "OPEN TO \nGRADING NOW",
                "href" => $this->core->buildUrl([
                    'component' => 'admin',
                    'page' => 'admin_gradeable',
                    'action' => 'quick_link',
                    'id' => $gradeable->getId(),
                    'quick_link_action' => 'open_grading_now']),
                "class" => "btn btn-primary btn-nav btn-nav-open"
            ]);
        }

        if ($button !== null) {
            return $button;
        }

        return null;
    }

    public function deleteGradeableForm() {
        return $this->core->getOutput()->renderTwigTemplate("navigation/DeleteGradeableForm.twig");
    }

}
