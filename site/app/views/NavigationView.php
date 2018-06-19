<?php
namespace app\views;
use app\libraries\Button;
use \app\libraries\GradeableType;
use app\models\Gradeable;
use app\models\GradeableList;


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
        return $this->core->getOutput()->renderTwigTemplate("error/NoAccessCourse.twig");
    }

    public function showGradeables($sections_to_list) {
        $return = "";

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
        // CREATE NEW GRADEABLE BUTTON -- only visible to instructors
        // ======================================================================================
        if ($this->core->getUser()->accessAdmin()) {
            $top_buttons[] = new Button([
                "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'view_gradeable_page')),
                "title" => "New Gradeable",
                "class" => "btn btn-primary"
            ]);
            $top_buttons[] = new Button([
                "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'gradeable', 'action' => 'upload_config')),
                "title" => "Upload Config & Review Build Output",
                "class" => "btn btn-primary"
            ]);

        }
        // ======================================================================================
        // LATE DAYS TABLE BUTTON
        // ======================================================================================

        $top_buttons[] = new Button([
            "href" => $this->core->buildUrl(array('component' => 'student', 'page' => 'view_late_table')),
            "title" => "Show my late days information",
            "class" => "btn btn-primary"
        ]);
        // ======================================================================================
        // FORUM BUTTON
        // ====================================================================================== 

        if ($this->core->getConfig()->isForumEnabled()) {
            $top_buttons[] = new Button([
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
            $top_buttons[] = new Button([
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

                $render_gradeables[] = [
                    "id" => $gradeable->getId(),
                    "name" => $gradeable->getName(),
                    "url" => $gradeable->getInstructionsURL(),
                    "can_delete" => $this->core->getUser()->accessAdmin() && $gradeable->canDelete(),
                    "buttons" => $this->getButtons($gradeable, $list_section)
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
     * @param int $list_section
     * @return array
     */
    private function getButtons(Gradeable $gradeable, int $list_section): array {
        $buttons = [];
        $buttons[] = $this->hasTeamButton($gradeable) ? $this->getTeamButton($gradeable) : null;
        $buttons[] = $this->hasSubmitButton($gradeable) ? $this->getSubmitButton($gradeable, $list_section): null;

        //Grade button if we can access grading
        if (($this->core->getUser()->accessGrading() && ($this->core->getUser()->getGroup() <= $gradeable->getMinimumGradingGroup())) || ($this->core->getUser()->getGroup() === 4 && $gradeable->getPeerGrading())) {
            $buttons[] = $this->hasGradeButton($gradeable) ? $this->getGradeButton($gradeable, $list_section) : null;
        }

        //Admin buttons
        if ($this->core->getUser()->accessAdmin()) {
            $buttons[] = $this->hasEditButton() ? $this->getEditButton($gradeable) : null;
            $buttons[] = $this->hasRebuildButton($gradeable) ? $this->getRebuildButton($gradeable) : null;
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
        return $this->core->getUser()->accessGrading() || $gradeable->getPeerGrading();
    }

    /**
     * @return bool
     */
    private function hasEditButton(): bool {
        return $this->core->getUser()->accessAdmin();
    }

    /**
     * @param Gradeable $gradeable
     * @return bool
     */
    private function hasRebuildButton(Gradeable $gradeable): bool {
        return ($this->core->getUser()->accessAdmin()) && ($gradeable->getType() == GradeableType::ELECTRONIC_FILE);
    }

    /**
     * @return bool
     */
    private function hasQuickLinkButton(): bool {
        return $this->core->getUser()->accessAdmin();
    }

    /**
     * @param Gradeable $gradeable
     * @return Button|null
     */
    private function getTeamButton(Gradeable $gradeable) {
        // Team management button, only visible on team assignments
        $date = new \DateTime("now", $this->core->getConfig()->getTimezone());
        $past_lock_date = $date->format('Y-m-d H:i:s') < $gradeable->getTeamLockDate()->format('Y-m-d H:i:s');

        if ($past_lock_date) {
            $team_display_date = "(teams lock {$gradeable->getTeamLockDate()->format(self::DATE_FORMAT)})";
        } else {
            $team_display_date = '';
        }

        if ($gradeable->getTeam() === null) {
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

        $button = new Button([
            "title" => $team_button_text,
            "subtitle" => $team_display_date,
            "href" => $this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId(), 'page' => 'team')),
            "class" => "btn {$team_button_type} btn-nav"
        ]);

        return $button;
    }

    /**
     * @param Gradeable $gradeable
     * @param int $list_section
     * @return Button|null
     */
    private function getSubmitButton(Gradeable $gradeable, int $list_section) {
        $class = self::gradeableSections[$list_section]["button_type_submission"];

        $href = $this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId()));
        $progress = null;
        $disabled = false;

        //Button types that override any other buttons
        if (!$gradeable->hasConfig()) {
            $button = new Button([
                "title" => "Need to run BUILD_{$this->core->getConfig()->getCourse()}.sh",
                "disabled" => true,
                "class" => "btn btn-default btn-nav"
            ]);

            return $button;
        }

        //calculate the point percentage
        if ($gradeable->getTotalNonHiddenNonExtraCreditPoints() == 0) {
            $points_percent = 0;
        } else {
            $points_percent = $gradeable->getGradedNonHiddenPoints() / $gradeable->getTotalNonHiddenNonExtraCreditPoints();
        }
        $points_percent = $points_percent * 100;
        if ($points_percent > 100) {
            $points_percent = 100;
        }

        //If the button is autograded and has been submitted once, give a progress bar.
        if ($gradeable->beenAutograded() && $gradeable->getTotalNonHiddenNonExtraCreditPoints() != 0 && $gradeable->getActiveVersion() >= 1
            && ($list_section == GradeableList::CLOSED || $list_section == GradeableList::OPEN)) {
            $progress = $points_percent;
        }

        if ($gradeable->getActiveVersion() < 1 && ($list_section == GradeableList::GRADED || $list_section == GradeableList::GRADING)) {
            //You forgot to submit
            $class = "btn-danger";
        }
        if ($gradeable->useTAGrading() && $gradeable->beenTAgraded() && $gradeable->getUserViewedDate() === null && $list_section === GradeableList::GRADED) {
            //Graded and you haven't seen it yet
            $class = "btn-success";
        }

        if ($gradeable->getType() == GradeableType::ELECTRONIC_FILE) {
            if ($list_section == GradeableList::GRADED && $gradeable->useTAGrading() && !$gradeable->beenTAgraded() && $gradeable->getActiveVersion() > 0) {
                $class = "btn-default";
            } else if ($list_section == GradeableList::GRADED && !$gradeable->useTAGrading() && $gradeable->getActiveVersion() > 0) {
                $class = "btn-default";
            }
        }

        if ($gradeable->beenAutograded() && $gradeable->getTotalNonHiddenNonExtraCreditPoints() != 0 && $gradeable->getActiveVersion() >= 1
            && $list_section == GradeableList::CLOSED && $points_percent >= 50) {
            $class = "btn-default";
        }

        $display_date = ($list_section == GradeableList::FUTURE || $list_section == GradeableList::BETA) ? "(opens " . $gradeable->getOpenDate()->format(self::DATE_FORMAT) . ")" : "(due " . $gradeable->getDueDate()->format(self::DATE_FORMAT) . ")";
        if ($gradeable->getActiveVersion() > 0 && ($list_section == GradeableList::GRADED || $list_section == GradeableList::GRADING)) {
            $display_date = "";
        }

        $title = self::gradeableSections[$list_section]["prefix"];
        if ($gradeable->isTeamAssignment() && $gradeable->getTeam() === null && !$this->core->getUser()->accessAdmin()) {
            //team assignment, no team (non-admin)
            $title = "MUST BE ON A TEAM TO SUBMIT";
            $disabled = true;
        } else if ($gradeable->getActiveVersion() >= 1 && $list_section == GradeableList::OPEN) {
            //if the user submitted something on time
            $title = "RESUBMIT";
        } else if ($gradeable->getActiveVersion() >= 1 && $list_section == GradeableList::CLOSED) {
            //if the user submitted something past time
            $title = "LATE RESUBMIT";
        } else if (($list_section == GradeableList::GRADED || $list_section == GradeableList::GRADING) && $gradeable->getActiveVersion() < 1) {
            //to change the text to overdue submission if nothing was submitted on time
            $title = "OVERDUE SUBMISSION";
        } else if ($list_section == GradeableList::GRADED && $gradeable->useTAGrading() && !$gradeable->beenTAgraded()) {
            //when there is no TA grade and due date passed
            $title = "TA GRADE NOT AVAILABLE";
        }

        $button = new Button([
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
        if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
            $href = $this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'gradeable_id' => $gradeable->getId()));
        } else if ($gradeable->getType() === GradeableType::CHECKPOINTS) {
            $href = $this->core->buildUrl(array('component' => 'grading', 'page' => 'simple', 'action' => 'lab', 'g_id' => $gradeable->getId()));
        } else if ($gradeable->getType() === GradeableType::NUMERIC_TEXT) {
            $href = $this->core->buildUrl(array('component' => 'grading', 'page' => 'simple', 'action' => 'numeric', 'g_id' => $gradeable->getId()));
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
            if (!$gradeable->hasConfig()) {
                $button = new Button([
                    "title" => "Need to run BUILD_{$this->core->getConfig()->getCourse()}.sh",
                    "disabled" => true,
                    "class" => "btn btn-default btn-nav"
                ]);

                return $button;
            }

            if ($this->core->getQueries()->getNumberRegradeRequests($gradeable->getId()) !== 0) {
                //Open regrade requests
                $button = new Button([
                    "title" => "REGRADE",
                    "class" => "btn btn-success btn-nav btn-nav-grade",
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
                if ($gradeable->useTAGrading()) {
                    list($components_total, $TA_percent) = $this->getTAPercent($gradeable);

                    if ($TA_percent === 100) {
                        //If they're done, change the text to REGRADE
                        $class = 'btn-default';
                        $title = 'REGRADE';
                    } else {
                        if ($components_total !== 0 && $list_section === GradeableList::GRADED) {
                            //You forgot somebody
                            $class = 'btn-danger';
                            $title = 'GRADE';
                        }
                    }

                    //Give the TAs a progress bar too
                    if ($components_total !== 0) {
                        $progress = $TA_percent;
                    }
                } else {
                    $title = "VIEW SUBMISSIONS";
                }
            } else {
                //Labs & Tests don't have exciting buttons
                $class = 'btn-default';
            }
        } else {
            if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE && !$gradeable->useTAGrading()) {
                $title = "VIEW SUBMISSIONS";
                $date_text = "(no manual grading)";
            } else {
                //Before grading has opened, only thing we can do is preview
                $title = 'PREVIEW GRADING';
                $date_text = '(grading opens ' . $gradeable->getGradeStartDate()->format(self::DATE_FORMAT) . ")";
            }
        }

        $button = new Button([
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
        $button = new Button([
            "title" => "Edit",
            "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'edit_gradeable_page', 'id' => $gradeable->getId())),
            "class" => "btn btn-default btn-nav"
        ]);
        return $button;
    }

    /**
     * @param Gradeable $gradeable
     * @return Button|null
     */
    private function getRebuildButton(Gradeable $gradeable) {
        $button = new Button([
            "title" => "Rebuild",
            "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'rebuild_assignement', 'id' => $gradeable->getId())),
            "class" => "btn btn-default btn-nav"
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
            $button = new Button([
                "title" => "RELEASE GRADES NOW",
                "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'quick_link', 'id' => $gradeable->getId(), 'quick_link_action' => 'release_grades_now')),
                "class" => "btn btn-primary btn-nav"
            ]);
        } else if ($list_section === GradeableList::FUTURE) {
            $button = new Button([
                "title" => "OPEN TO TAS NOW",
                "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'quick_link', 'id' => $gradeable->getId(), 'quick_link_action' => 'open_ta_now')),
                "class" => "btn btn-primary btn-nav"
            ]);
        } else if ($list_section === GradeableList::BETA) {
            if ($gradeable->getType() == GradeableType::ELECTRONIC_FILE) {
                $button = new Button([
                    "title" => "OPEN NOW",
                    "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'quick_link', 'id' => $gradeable->getId(), 'quick_link_action' => 'open_students_now')),
                    "class" => "btn btn-primary btn-nav"
                ]);
            } else {
                $button = new Button([
                    "title" => "OPEN TO GRADING NOW",
                    "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'quick_link', 'id' => $gradeable->getId(), 'quick_link_action' => 'open_grading_now')),
                    "class" => "btn btn-primary btn-nav"
                ]);
            }
        } else if ($list_section === GradeableList::CLOSED) {
            $button = new Button([
                "title" => "OPEN TO GRADING NOW",
                "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'quick_link', 'id' => $gradeable->getId(), 'quick_link_action' => 'open_grading_now')),
                "class" => "btn btn-primary btn-nav"
            ]);
        }

        if ($button !== null) {
            return $button;
        }

        return null;
    }

    /**
     * @param Gradeable $gradeable
     * @return array
     */
    private function getTAPercent(Gradeable $gradeable): array {
        $gradeable_id = $gradeable->getId();

        //This code is taken from the ElectronicGraderController, it used to calculate the TA percentage.
        $total_users = array();
        $no_team_users = array();
        $graded_components = array();
        $graders = array();
        if ($gradeable->isGradeByRegistration()) {
            if (!$this->core->getUser()->accessFullGrading()) {
                $sections = $this->core->getUser()->getGradingRegistrationSections();
            } else {
                $sections = $this->core->getQueries()->getRegistrationSections();
                foreach ($sections as $i => $section) {
                    $sections[$i] = $section['sections_registration_id'];
                }
            }
            $section_key = 'registration_section';
            if (count($sections) > 0) {
                $graders = $this->core->getQueries()->getGradersForRegistrationSections($sections);
            }
        } else {
            if (!$this->core->getUser()->accessFullGrading()) {
                $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id, $this->core->getUser()->getId());
            } else {
                $sections = $this->core->getQueries()->getRotatingSections();
                foreach ($sections as $i => $section) {
                    $sections[$i] = $section['sections_rotating_id'];
                }
            }
            $section_key = 'rotating_section';
            if (count($sections) > 0) {
                $graders = $this->core->getQueries()->getGradersForRotatingSections($gradeable_id, $sections);
            }
        }
        if (count($sections) > 0) {
            if ($gradeable->isTeamAssignment()) {
                $total_users = $this->core->getQueries()->getTotalTeamCountByGradingSections($gradeable_id, $sections, $section_key);
                $no_team_users = $this->core->getQueries()->getUsersWithoutTeamByGradingSections($gradeable_id, $sections, $section_key);
                $graded_components = $this->core->getQueries()->getGradedComponentsCountByTeamGradingSections($gradeable_id, $sections, $section_key);
            } else {
                $total_users = $this->core->getQueries()->getTotalUserCountByGradingSections($sections, $section_key);
                $no_team_users = array();
                $graded_components = $this->core->getQueries()->getGradedComponentsCountByGradingSections($gradeable_id, $sections, $section_key, $gradeable->isTeamAssignment());
            }
        }

        $num_components = $this->core->getQueries()->getTotalComponentCount($gradeable_id);
        $num_submitted = $this->core->getQueries()->getTotalSubmittedUserCountByGradingSections($gradeable_id, $sections, $section_key);
        $sections = array();
        if (count($total_users) > 0) {
            foreach ($num_submitted as $key => $value) {
                $sections[$key] = array(
                    'total_components' => $value * $num_components,
                    'graded_components' => 0,
                    'graders' => array()
                );
                if ($gradeable->isTeamAssignment()) {
                    $sections[$key]['no_team'] = $no_team_users[$key];
                }
                if (isset($graded_components[$key])) {
                    // Clamp to total components if unsubmitted assigment is graded for whatever reason
                    $sections[$key]['graded_components'] = min(intval($graded_components[$key]), $sections[$key]['total_components']);
                }
                if (isset($graders[$key])) {
                    $sections[$key]['graders'] = $graders[$key];
                }
            }
        }
        $components_graded = 0;
        $components_total = 0;
        foreach ($sections as $key => $section) {
            if ($key === "NULL") {
                continue;
            }
            $components_graded += $section['graded_components'];
            $components_total += $section['total_components'];
        }
        if ($components_total == 0) {
            $TA_percent = 0;
        } else {
            $TA_percent = $components_graded / $components_total;
            $TA_percent = $TA_percent * 100;
        }
        return array($components_total, $TA_percent);
    }

    public function deleteGradeableForm() {
        return $this->core->getOutput()->renderTwigTemplate("navigation/DeleteGradeableForm.twig");
    }

}
