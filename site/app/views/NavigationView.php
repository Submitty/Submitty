<?php
namespace app\views;
use app\libraries\Button;
use \app\libraries\GradeableType;
use app\models\Gradeable;
use app\libraries\FileUtils;
use app\models\GradeableSection;


class NavigationView extends AbstractView {

    const gradeableSections = [
        GradeableSection::FUTURE => [
            //What title is displayed to the user for each category
            "title" => "FUTURE &nbsp;&nbsp; <em>visible only to Instructors</em>",
            //What bootstrap button the student button will be. Information about bootstrap buttons can be found here:
            //https://www.w3schools.com/bootstrap/bootstrap_buttons.asp
            "button_type_submission" => "btn-default",
            //What bootstrap button the instructor/TA button will be
            "button_type_grading" => "btn-default",
            //The general text of the button under the category
            //It is general since the text could change depending if the user submitted something or not and other factors.
            "prefix" => "ALPHA SUBMIT"
        ],
        GradeableSection::BETA => [
            "title" => "BETA &nbsp;&nbsp; <em>open for testing by TAs</em>",
            "button_type_submission" => "btn-default",
            "button_type_grading" => "btn-default",
            "prefix" => "BETA SUBMIT"
        ],
        GradeableSection::OPEN => [
            "title" => "OPEN",
            "button_type_submission" => "btn-primary",
            "button_type_grading" => "btn-default",
            "prefix" => "SUBMIT"
        ],
        GradeableSection::CLOSED => [
            "title" => "PAST DUE",
            "button_type_submission" => "btn-danger",
            "button_type_grading" => "btn-default",
            "prefix" => "LATE SUBMIT"
        ],
        GradeableSection::GRADING => [
            "title" => "CLOSED &nbsp;&nbsp; <em>being graded by TA/Instructor</em>",
            "button_type_submission" => "btn-default",
            "button_type_grading" => "btn-primary",
            "prefix" => "VIEW SUBMISSION"
        ],
        GradeableSection::GRADED => [
            "title" => "GRADES AVAILABLE",
            "button_type_submission" => 'btn-success',
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
        if ($display_custom_message && $message_file_contents != "") {
            $return .= <<<HTML
<div class="content">
   {$message_file_contents}
</div>
HTML;
        }
        $return .= <<<HTML
<div class="content">
    <div class="nav-buttons">
HTML;

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

        foreach ($top_buttons as $button) {
            $return .= $this->renderButton($button) . " ";
        }

        $return .= <<<HTML
    </div>
HTML;
        // ======================================================================================
        // INDEX OF ALL GRADEABLES
        // ======================================================================================
        $return .= <<<HTML
    <table class="gradeable_list" style="width:100%;">
HTML;

        $found_assignment = false;
        foreach ($sections_to_list as $list_section => $gradeable_list) {
            /** @var Gradeable[] $gradeable_list */

            $found_assignment = true;
            $section_id = str_replace(" ", "_", strtolower($list_section));
            $return .= <<<HTML
        <tr class="bar"><td colspan="10"></td></tr>
        <tr class="colspan nav-title-row" id="{$section_id}"><td colspan="4">{$this::gradeableSections[$list_section]["title"]}</td></tr>
        <tbody id="{$section_id}_tbody">
HTML;
            foreach ($gradeable_list as $gradeable_id => $gradeable) {
                /** @var Gradeable $gradeable */

                $buttons = [];

                $gradeable_title         = $this->getTitleCell($gradeable);
                $gradeable_team_button   = $this->hasTeamButton($gradeable)    ? $this->getTeamButton($gradeable)                     : "";
                $gradeable_open_button   = $this->hasSubmitButton($gradeable)  ? $this->getSubmitButton($gradeable, $list_section)    : "";
                $gradeable_grade_button  = $this->hasGradeButton($gradeable)   ? $this->getGradeButton($gradeable, $list_section)     : "";
                $admin_edit_button       = $this->hasEditButton()              ? $this->getEditButton($gradeable)                     : "";
                $admin_rebuild_button    = $this->hasRebuildButton($gradeable) ? $this->getRebuildButton($gradeable)                  : "";
                $admin_quick_link_button = $this->hasQuickLinkButton()         ? $this->getQuickLinkButton($gradeable, $list_section) : "";

                $return .= <<<HTML
            <tr class="gradeable_row">
                <td>{$gradeable_title}</td>
                <td style="padding: 20px;">{$gradeable_team_button}</td>
                <td style="padding: 20px;">{$gradeable_open_button}</td>
HTML;
                if (($this->core->getUser()->accessGrading() && ($this->core->getUser()->getGroup() <= $gradeable->getMinimumGradingGroup())) || ($this->core->getUser()->getGroup() === 4 && $gradeable->getPeerGrading())) {
                    $return .= <<<HTML
                <td style="padding: 20px;">{$gradeable_grade_button}</td>
                <td style="padding: 20px;">{$admin_edit_button}</td>
                <td style="padding: 20px;">{$admin_rebuild_button}</td>
                <td style="padding: 20px;">{$admin_quick_link_button}</td>
HTML;
                }
                $return .= <<<HTML
            </tr>
HTML;
            }
            $return .= '</tbody><tr class="colspan"><td colspan="10" style="border-bottom:2px black solid;"></td></tr>';
        }
        if ($found_assignment == false) {
            $return .= <<<HTML
    <div class="container">
    <p>There are currently no assignments posted.  Please check back later.</p>
    </div></table></div>
HTML;
            return $return;
        }
        $return .= <<<HTML
                            </table>
                        </div>
HTML;
        return $return;
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
     * @return string
     */
    private function getTitleCell(Gradeable $gradeable): string {
        $gradeable_title = $gradeable_title = '<label>' . $gradeable->getName() . '</label>';
        if (trim($gradeable->getInstructionsURL()) != '') {
            $gradeable_title .= '<a class="external" href="' . $gradeable->getInstructionsURL() . '" target="_blank"><i style="margin-left: 10px;" class="fa fa-external-link"></i></a>';
        }

        if ($this->core->getUser()->accessAdmin() && $gradeable->canDelete()) {
            $form_action = $this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'delete_gradeable', 'id' => $gradeable->getId()));
            $gradeable_title .= <<<HTML
                    <i class="fa fa-times" style="color:red; cursor:pointer;" aria-hidden="true" onclick='newDeleteGradeableForm("{$form_action}","{$gradeable->getName()}");'></i>
HTML;

        }
        return $gradeable_title;
    }

    /**
     * @param Gradeable $gradeable
     * @return string
     */
    private function getTeamButton(Gradeable $gradeable): string {
        // Team management button, only visible on team assignments
        $date = new \DateTime("now", $this->core->getConfig()->getTimezone());
        $past_lock_date = $date->format('Y-m-d H:i:s') < $gradeable->getTeamLockDate()->format('Y-m-d H:i:s');

        if ($past_lock_date) {
            $team_display_date = "<br><span style=\"font-size:smaller;\">(teams lock {$gradeable->getTeamLockDate()->format(self::DATE_FORMAT)})</span>";
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
            "title" => $team_button_text . $team_display_date,
            "href" => $this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId(), 'page' => 'team')),
            "class" => "btn {$team_button_type} btn-nav"
        ]);

        return $this->renderButton($button);
    }

    /**
     * @param Gradeable $gradeable
     * @param int $list_section
     * @return string
     */
    private function getSubmitButton(Gradeable $gradeable, int $list_section): string {
        $button_type_submission = self::gradeableSections[$list_section]["button_type_submission"];

        if ($gradeable->getActiveVersion() < 1) {
            if ($list_section == GradeableSection::GRADED || $list_section == GradeableSection::GRADING) {
                $button_type_submission = self::gradeableSections[GradeableSection::CLOSED]["button_type_submission"];
            }
        }
        if ($gradeable->useTAGrading() && $gradeable->beenTAgraded() && $gradeable->getUserViewedDate() !== null && $list_section === GradeableSection::GRADED) {
            $button_type_submission = "btn-default";
        }

        if ($gradeable->getType() == GradeableType::ELECTRONIC_FILE) {
            if ($list_section == GradeableSection::GRADED && $gradeable->useTAGrading() && !$gradeable->beenTAgraded() && $gradeable->getActiveVersion() > 0) {
                $button_type_submission = "btn-default";
            } else if ($list_section == GradeableSection::GRADED && !$gradeable->useTAGrading() && $gradeable->getActiveVersion() > 0) {
                $button_type_submission = "btn-default";
            }
        }

        $submit_display_date = ($list_section == GradeableSection::FUTURE || $list_section == GradeableSection::BETA) ? "(opens " . $gradeable->getOpenDate()->format(self::DATE_FORMAT) . ")" : "(due " . $gradeable->getDueDate()->format(self::DATE_FORMAT) . ")";
        if ($gradeable->getActiveVersion() > 0 && ($list_section == GradeableSection::GRADED || $list_section == GradeableSection::GRADING)) {
            $submit_display_date = "";
        }

        $submit_button_text = self::gradeableSections[$list_section]["prefix"];
        if ($gradeable->getActiveVersion() >= 1 && $list_section == GradeableSection::OPEN) {
            //if the user submitted something on time
            $submit_button_text = "RESUBMIT";
        } else if ($gradeable->getActiveVersion() >= 1 && $list_section == GradeableSection::CLOSED) {
            //if the user submitted something past time
            $submit_button_text = "LATE RESUBMIT";
        } else if (($list_section == GradeableSection::GRADED || $list_section == GradeableSection::GRADING) && $gradeable->getActiveVersion() < 1) {
            //to change the text to overdue submission if nothing was submitted on time
            $submit_button_text = "OVERDUE SUBMISSION";
        } else if ($list_section == GradeableSection::GRADED && $gradeable->useTAGrading() && !$gradeable->beenTAgraded()) {
            //when there is no TA grade and due date passed
            $submit_button_text = "TA GRADE NOT AVAILABLE";
        }

        if ($gradeable->hasConfig()) {
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
            if (($gradeable->isTeamAssignment() && $gradeable->getTeam() === null) && (!$this->core->getUser()->accessAdmin())) {
                $gradeable_open_range = <<<HTML
                <a class="btn {$button_type_submission} btn-nav btn-nav-submit" disabled>
                     MUST BE ON A TEAM TO SUBMIT<br>{$submit_display_date}
                </a>
HTML;
            } else if ($gradeable->beenAutograded() && $gradeable->getTotalNonHiddenNonExtraCreditPoints() != 0 && $gradeable->getActiveVersion() >= 1
                && $list_section == GradeableSection::CLOSED && $points_percent >= 50) {
                $gradeable_open_range = <<<HTML
                 <a class="btn btn-default btn-nav btn-nav-submit" href="{$this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId()))}">
                     {$submit_button_text}<br>{$submit_display_date}
                 </a>
HTML;
            } else {
                $gradeable_open_range = <<<HTML
                 <a class="btn {$button_type_submission} btn-nav btn-nav-submit" href="{$this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId()))}">
                     {$submit_button_text}<br>{$submit_display_date}
                 </a>
HTML;
            }


            //If the button is autograded and has been submitted once, give a progress bar.
            if ($gradeable->beenAutograded() && $gradeable->getTotalNonHiddenNonExtraCreditPoints() != 0 && $gradeable->getActiveVersion() >= 1
                && ($list_section == GradeableSection::CLOSED || $list_section == GradeableSection::OPEN)) {

                if ($points_percent >= 50) {
                    $gradeable_open_range .= $this->getProgressBar($points_percent);
                } else {
                    //Give them an imaginary progress point
                    if ($gradeable->getGradedNonHiddenPoints() == 0) {
                        $gradeable_open_range .= $this->getProgressBar(2);
                    } else {
                        $gradeable_open_range .= $this->getProgressBar($points_percent);
                    }
                }
            }
        } else {
            $gradeable_open_range = <<<HTML
                 <a class="btn {$button_type_submission}" style="width:100%;" disabled>
                     Need to run BUILD_{$this->core->getConfig()->getCourse()}.sh
                 </a>
HTML;
        }
        return $gradeable_open_range;
    }

    /**
     * @param Gradeable $gradeable
     * @param int $list_section
     * @return string
     */
    private function getGradeButton(Gradeable $gradeable, int $list_section): string {
        //Location, location never changes
        if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
            $href = $this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'gradeable_id' => $gradeable->getId()));
        } else if ($gradeable->getType() === GradeableType::CHECKPOINTS) {
            $href = $this->core->buildUrl(array('component' => 'grading', 'page' => 'simple', 'action' => 'lab', 'g_id' => $gradeable->getId()));
        } else if ($gradeable->getType() === GradeableType::NUMERIC_TEXT) {
            $href = $this->core->buildUrl(array('component' => 'grading', 'page' => 'simple', 'action' => 'numeric', 'g_id' => $gradeable->getId()));
        }

        //Default values
        $button_type_grading = self::gradeableSections[$list_section]["button_type_grading"];
        $date_text = null;
        $disabled = false;
        $progress = null;

        //Button types that override any other buttons
        if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
            if (!$gradeable->hasConfig()) {
                $button = new Button([
                    "title" => "Need to run BUILD_{$this->core->getConfig()->getCourse()}.sh",
                    "disabled" => true,
                    "class" => "btn btn-default btn-nav"
                ]);

                return $this->renderButton($button);
            }

            if ($this->core->getQueries()->getNumberRegradeRequests($gradeable->getId()) !== 0) {
                //Open regrade requests
                $button = new Button([
                    "title" => "REGRADE",
                    "class" => "btn btn-success btn-nav btn-nav-grade",
                    "href" => $href
                ]);

                return $this->renderButton($button);
            }
        }

        if ($list_section === GradeableSection::GRADING || $list_section === GradeableSection::GRADED) {
            if ($list_section === GradeableSection::GRADING) {
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
                        $button_type_grading = 'btn-default';
                        $title = 'REGRADE';
                    } else {
                        if ($components_total !== 0 && $list_section === GradeableSection::GRADED) {
                            //You forgot somebody
                            $button_type_grading = 'btn-danger';
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
                $button_type_grading = 'btn-default';
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
            "class" => "btn btn-nav btn-nav-grade {$button_type_grading}",
            "disabled" => $disabled
        ]);

        return $this->renderButton($button);
    }

    /**
     * @param Gradeable $gradeable
     * @return string
     */
    private function getEditButton(Gradeable $gradeable): string {
        $button = new Button([
            "title" => "Edit",
            "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'edit_gradeable_page', 'id' => $gradeable->getId())),
            "class" => "btn btn-default btn-nav"
        ]);
        return $this->renderButton($button);
    }

    /**
     * @param Gradeable $gradeable
     * @return string
     */
    private function getRebuildButton(Gradeable $gradeable): string {
        $button = new Button([
            "title" => "Rebuild",
            "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'rebuild_assignement', 'id' => $gradeable->getId())),
            "class" => "btn btn-default btn-nav"
        ]);
        return $this->renderButton($button);
    }

    /**
     * @param Gradeable $gradeable
     * @param int $list_section
     * @return string
     */
    private function getQuickLinkButton(Gradeable $gradeable, int $list_section): string {
        $button = null;
        if ($list_section === GradeableSection::GRADING) {
            $button = new Button([
                "title" => "RELEASE GRADES NOW",
                "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'quick_link', 'id' => $gradeable->getId(), 'quick_link_action' => 'release_grades_now')),
                "class" => "btn btn-primary btn-nav"
            ]);
        } else if ($list_section === GradeableSection::FUTURE) {
            $button = new Button([
                "title" => "OPEN TO TAS NOW",
                "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'quick_link', 'id' => $gradeable->getId(), 'quick_link_action' => 'open_ta_now')),
                "class" => "btn btn-primary btn-nav"
            ]);
        } else if ($list_section === GradeableSection::BETA) {
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
        } else if ($list_section === GradeableSection::CLOSED) {
            $button = new Button([
                "title" => "OPEN TO GRADING NOW",
                "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'quick_link', 'id' => $gradeable->getId(), 'quick_link_action' => 'open_grading_now')),
                "class" => "btn btn-primary btn-nav"
            ]);
        }

        if ($button !== null) {
            return $this->renderButton($button);
        }

        return "";
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
        $TA_percent = 0;
        if ($components_total == 0) {
            $TA_percent = 0;
        } else {
            $TA_percent = $components_graded / $components_total;
            $TA_percent = $TA_percent * 100;
        }
        return array($components_total, $TA_percent);
    }

    /**
     * @param float $percent
     * @return string
     */
    private function getProgressBar(float $percent):string {
        return "<div class=\"meter\"><span style=\"width: {$percent}%\"></span></div>";
    }

    private function renderButton(Button $button) {
        $html = "<a class=\"{$button->getClass()}\" href=\"{$button->getHref()}\">";
        $html .= $button->getTitle();
        if ($button->getSubtitle() !== null) {
            $html .= "<br><span style=\"font-size:smaller;\">{$button->getSubtitle()}</span>";
        }
        $html .= "</a>";
        if ($button->getProgress() !== null) {
            $html .= $this->getProgressBar($button->getProgress());
        }

        return $html;
    }

    public function deleteGradeableForm() {
        return $this->core->getOutput()->renderTwigTemplate("navigation/DeleteGradeableForm.twig");
    }

}
