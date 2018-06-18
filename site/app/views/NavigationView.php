<?php
namespace app\views;
use \app\libraries\GradeableType;
use app\models\Gradeable;
use app\libraries\FileUtils;


class NavigationView extends AbstractView {

    const gradeableSections = [
        [
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
        [
            "title" => "BETA &nbsp;&nbsp; <em>open for testing by TAs</em>",
            "button_type_submission" => "btn-default",
            "button_type_grading" => "btn-default",
            "prefix" => "BETA SUBMIT"
        ],
        [
            "title" => "OPEN",
            "button_type_submission" => "btn-primary" ,
            "button_type_grading" => "btn-default" ,
            "prefix" => "SUBMIT"
        ],
        [
            "title" => "PAST DUE",
            "button_type_submission" => "btn-danger",
            "button_type_grading" => "btn-default",
            "prefix" => "LATE SUBMIT"
        ],
        [
            "title" => "CLOSED &nbsp;&nbsp; <em>being graded by TA/Instructor</em>",
            "button_type_submission" => "btn-default",
            "button_type_grading" => "btn-primary",
            "prefix" => "VIEW SUBMISSION"
        ],
        [
            "title" => "GRADES AVAILABLE",
            "button_type_submission" => 'btn-success',
            "button_type_grading" => 'btn-danger',
            "prefix" => "VIEW GRADE"
        ]
    ];

    const FUTURE = "FUTURE";
    const BETA = "BETA";
    const OPEN = "OPEN";
    const CLOSED = "CLOSED";
    const ITEMS_BEING_GRADED = "ITEMS BEING GRADED";
    const GRADED = "GRADED";

    const sectionMap = [
        self::FUTURE => 0,
        self::BETA => 1,
        self::OPEN => 2,
        self::CLOSED => 3,
        self::ITEMS_BEING_GRADED => 4,
        self::GRADED => 5
    ];

    const DATE_FORMAT = "m/d/Y @ H:i";

    public function noAccessCourse() {
        return <<<HTML
<div class="content">
   You don't have access to {$this->core->getDisplayedCourseName()}. If you think this is mistake,
   please contact your instructor to gain access.
</div>
HTML;
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
        // ======================================================================================
        // CREATE NEW GRADEABLE BUTTON -- only visible to instructors
        // ======================================================================================
        if ($this->core->getUser()->accessAdmin()) {
            $return .= <<<HTML
        <a class="btn btn-primary" href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'view_gradeable_page'))}">New Gradeable</a>
        <a class="btn btn-primary" href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'gradeable', 'action' => 'upload_config'))}">Upload Config & Review Build Output</a>

HTML;
        }
        // ======================================================================================
        // LATE DAYS TABLE BUTTON
        // ======================================================================================

        $return .= <<<HTML
        <a class="btn btn-primary" href="{$this->core->buildUrl(array('component' => 'student', 'page' => 'view_late_table'))}">Show my late days information</a>
HTML;
        // ======================================================================================
        // FORUM BUTTON
        // ====================================================================================== 

        if ($this->core->getConfig()->isForumEnabled()) {
            $return .= <<<HTML
            <a class="btn btn-primary" href="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread'))}">Discussion Forum</a>
HTML;
        }
        // ======================================================================================
        // GRADES SUMMARY BUTTON
        // ======================================================================================
        $display_rainbow_grades_summary = $this->core->getConfig()->displayRainbowGradesSummary();
        if ($display_rainbow_grades_summary) {
            $return .= <<<HTML
        <a class="btn btn-primary" href="{$this->core->buildUrl(array('component' => 'student', 'page' => 'rainbow'))}">View Grades</a>
HTML;
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

            $index = self::sectionMap[$list_section];

            $found_assignment = true;
            $section_id = str_replace(" ", "_", strtolower($list_section));
            $return .= <<<HTML
        <tr class="bar"><td colspan="10"></td></tr>
        <tr class="colspan nav-title-row" id="{$section_id}"><td colspan="4">{$this::gradeableSections[$index]["title"]}</td></tr>
        <tbody id="{$section_id}_tbody">
HTML;
            foreach ($gradeable_list as $gradeable_id => $gradeable) {
                /** @var Gradeable $gradeable */

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
        if (trim($gradeable->getInstructionsURL()) != '') {
            $gradeable_title = '<label>' . $gradeable->getName() . '</label><a class="external" href="' . $gradeable->getInstructionsURL() . '" target="_blank"><i style="margin-left: 10px;" class="fa fa-external-link"></i></a>';
        } else if ($gradeable->getType() == GradeableType::ELECTRONIC_FILE) {
            # no_team_flag is true if there are no teams else false. Note deleting a gradeable is not allowed is no_team_flag is false.
            $no_teams_flag = true;
            $all_teams = $this->core->getQueries()->getTeamsByGradeableId($gradeable->getId());
            if (!empty($all_teams)) {
                $no_teams_flag = false;
            }
            # no_submission_flag is true if there are no submissions for assignement else false. Note deleting a gradeable is not allowed is no_submission_flag is false.
            $no_submission_flag = true;
            $semester = $this->core->getConfig()->getSemester();
            $course = $this->core->getConfig()->getCourse();
            $submission_path = "/var/local/submitty/courses/" . $semester . "/" . $course . "/" . "submissions/" . $gradeable->getId();
            if (is_dir($submission_path)) {
                $no_submission_flag = false;
            }
            if ($this->core->getUser()->accessAdmin() && $no_submission_flag && $no_teams_flag) {
                $form_action = $this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'delete_gradeable', 'id' => $gradeable->getId()));
                $gradeable_title = <<<HTML
                    <label>{$gradeable->getName()}</label>&nbsp;
                    <i class="fa fa-times" style="color:red; cursor:pointer;" aria-hidden="true" onclick='newDeleteGradeableForm("{$form_action}","{$gradeable->getName()}");'></i>
HTML;
            } else {
                $gradeable_title = '<label>' . $gradeable->getName() . '</label>';
            }
        } else if (($gradeable->getType() == GradeableType::NUMERIC_TEXT) || (($gradeable->getType() == GradeableType::CHECKPOINTS))) {
            if ($this->core->getUser()->accessAdmin() && $this->core->getQueries()->getNumUsersGraded($gradeable->getId()) === 0) {
                $form_action = $this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'delete_gradeable', 'id' => $gradeable->getId()));
                $gradeable_title = <<<HTML
                    <label>{$gradeable->getName()}</label>&nbsp;
                    <i class="fa fa-times" style="color:red; cursor:pointer;" aria-hidden="true" onclick='newDeleteGradeableForm("{$form_action}","{$gradeable->getName()}");'></i>
HTML;
            } else {
                $gradeable_title = '<label>' . $gradeable->getName() . '</label>';
            }
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

        $gradeable_team_range = <<<HTML
                <a class="btn {$team_button_type}" style="width:100%;"
                href="{$this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId(), 'page' => 'team'))}">
                {$team_button_text}{$team_display_date}
HTML;
        return $gradeable_team_range;
    }

    /**
     * @param Gradeable $gradeable
     * @param string $list_section
     * @return string
     */
    private function getSubmitButton(Gradeable $gradeable, string $list_section): string {
        $button_type_submission = self::gradeableSections[self::sectionMap[$list_section]]["button_type_submission"];

        if ($gradeable->getActiveVersion() < 1) {
            if ($list_section == self::GRADED || $list_section == self::ITEMS_BEING_GRADED) {
                $button_type_submission = self::gradeableSections[self::sectionMap[self::CLOSED]]["button_type_submission"];
            }
        }
        if ($gradeable->useTAGrading() && $gradeable->beenTAgraded() && $gradeable->getUserViewedDate() !== null && $list_section === self::GRADED) {
            $button_type_submission = "btn-default";
        }

        if ($gradeable->getType() == GradeableType::ELECTRONIC_FILE) {
            if ($list_section == self::GRADED && $gradeable->useTAGrading() && !$gradeable->beenTAgraded() && $gradeable->getActiveVersion() > 0) {
                $button_type_submission = "btn-default";
            } else if ($list_section == self::GRADED && !$gradeable->useTAGrading() && $gradeable->getActiveVersion() > 0) {
                $button_type_submission = "btn-default";
            }
        }

        $submit_display_date = ($list_section == self::FUTURE || $list_section == self::BETA) ? "<span style=\"font-size:smaller;\">(opens " . $gradeable->getOpenDate()->format(self::DATE_FORMAT) . "</span>)" : "<span style=\"font-size:smaller;\">(due " . $gradeable->getDueDate()->format(self::DATE_FORMAT) . "</span>)";
        if ($gradeable->getActiveVersion() > 0 && ($list_section == self::GRADED || $list_section == self::ITEMS_BEING_GRADED)) {
            $submit_display_date = "";
        }

        $submit_button_text = self::gradeableSections[self::sectionMap[$list_section]]["prefix"];
        if ($gradeable->getActiveVersion() >= 1 && $list_section == self::OPEN) {
            //if the user submitted something on time
            $submit_button_text = "RESUBMIT";
        } else if ($gradeable->getActiveVersion() >= 1 && $list_section == self::CLOSED) {
            //if the user submitted something past time
            $submit_button_text = "LATE RESUBMIT";
        } else if (($list_section == self::GRADED || $list_section == self::ITEMS_BEING_GRADED) && $gradeable->getActiveVersion() < 1) {
            //to change the text to overdue submission if nothing was submitted on time
            $submit_button_text = "OVERDUE SUBMISSION";
        } else if ($list_section == self::GRADED && $gradeable->useTAGrading() && !$gradeable->beenTAgraded()) {
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
                <a class="btn {$button_type_submission} btn-nav" disabled>
                     MUST BE ON A TEAM TO SUBMIT<br>{$submit_display_date}
                </a>
HTML;
            } else if ($gradeable->beenAutograded() && $gradeable->getTotalNonHiddenNonExtraCreditPoints() != 0 && $gradeable->getActiveVersion() >= 1
                && $list_section == self::CLOSED && $points_percent >= 50) {
                $gradeable_open_range = <<<HTML
                 <a class="btn btn-default btn-nav" href="{$this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId()))}">
                     {$submit_button_text}<br>{$submit_display_date}
                 </a>
HTML;
            } else {
                $gradeable_open_range = <<<HTML
                 <a class="btn {$button_type_submission} btn-nav" href="{$this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId()))}">
                     {$submit_button_text}<br>{$submit_display_date}
                 </a>
HTML;
            }


            //If the button is autograded and has been submitted once, give a progress bar.
            if ($gradeable->beenAutograded() && $gradeable->getTotalNonHiddenNonExtraCreditPoints() != 0 && $gradeable->getActiveVersion() >= 1
                && ($list_section == self::CLOSED || $list_section == self::OPEN)) {

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
                 <button class="btn {$button_type_submission}" style="width:100%;" disabled>
                     Need to run BUILD_{$this->core->getConfig()->getCourse()}.sh
                 </button>
HTML;
        }
        return $gradeable_open_range;
    }

    /**
     * @param Gradeable $gradeable
     * @param string $list_section
     * @return string
     */
    private function getGradeButton(Gradeable $gradeable, string $list_section): string {
        $button_type_grading = self::gradeableSections[self::sectionMap[$list_section]]["button_type_grading"];

        if ($gradeable->getActiveVersion() < 1) {
            if ($list_section == self::GRADED || $list_section == self::ITEMS_BEING_GRADED) {
                $button_type_grading = self::gradeableSections[self::sectionMap[self::CLOSED]]["button_type_grading"];
            }
        }

        $button_title = 'PREVIEW GRADING';
        $date_text = '<br><span style="font-size:smaller;">(grading opens ' . $gradeable->getGradeStartDate()->format(self::DATE_FORMAT) . ")</span>";
        if ($gradeable->getType() == GradeableType::ELECTRONIC_FILE) {
            if ($gradeable->hasConfig()) {
                if ($gradeable->useTAGrading()) {
                    $button_title = 'PREVIEW GRADING';
                    $date_text = '<br><span style="font-size:smaller;">(grading opens ' . $gradeable->getGradeStartDate()->format(self::DATE_FORMAT) . "</span>)";
                } else {
                    $button_title = 'VIEW SUBMISSIONS';
                    $date_text = '<br><span style="font-size:smaller;">(<em>no manual grading</em></span>)';
                }
            } else {
                $button_title = "Need to run BUILD_{$this->core->getConfig()->getCourse()}.sh";
                $date_text = "";
            }
        }
        $temp_button_title = "";
        if ($list_section == self::ITEMS_BEING_GRADED) {
            $button_title = 'GRADE';
            $date_text = '<br><span style="font-size:smaller;">(grades due ' . $gradeable->getGradeReleasedDate()->format(self::DATE_FORMAT) . '</span>)';
            $temp_button_title = 'REGRADE';
            $temp_date_text = '<br><span style="font-size:smaller;">(grades due ' . $gradeable->getGradeReleasedDate()->format(self::DATE_FORMAT) . '</span>)';
        }
        if ($list_section == self::GRADED) {
            if ($gradeable->getType() == GradeableType::ELECTRONIC_FILE) {
                if ($gradeable->useTAGrading()) {
                    $button_title = 'GRADE';
                } else {
                    $button_title = 'VIEW SUBMISSIONS';
                }
            } else {
                $button_title = 'REGRADE';
            }
            $date_text = '';
        }

        if ($this->core->getQueries()->getNumberRegradeRequests($gradeable->getId()) !== 0) {
            //Open regrade requests
            $button_title = "REGRADE REQUESTS";
            $date_text = '';
            $grade_button_type = "btn-danger";
        }

        $regrade_button = "";

        if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
            if ($gradeable->hasConfig()) {
                list($components_total, $TA_percent) = $this->getTAPercent($gradeable);

                $grade_button_type = self::gradeableSections[self::sectionMap[$list_section]]["button_type_grading"];
                if (!$gradeable->useTAGrading() || $TA_percent === 100) {
                    $grade_button_type = 'btn-default';
                }

                //if $TA_percent is 100, change the text to REGRADE
                if ($TA_percent == 100 && $list_section == self::ITEMS_BEING_GRADED) {
                    $regrade_button = <<<HTML
                            <a class="btn {$grade_button_type} btn-nav"
                            href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'gradeable_id' => $gradeable->getId()))}">
                            {$button_title} {$date_text}</a>
HTML;
                } else if ($TA_percent == 100 && $list_section == self::GRADED) {
                    $regrade_button = <<<HTML
                            <a class="btn {$grade_button_type} btn-nav"
                            href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'gradeable_id' => $gradeable->getId()))}">
                            REGRADE</a>
HTML;
                } else {
                    $regrade_button = <<<HTML
                            <a class="btn {$grade_button_type} btn-nav"
                            href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'gradeable_id' => $gradeable->getId()))}">
                            {$button_title} {$date_text}</a>
HTML;
                }
                //Give the TAs a progress bar too
                if (($list_section == self::GRADED || $list_section == self::ITEMS_BEING_GRADED) && $components_total != 0 && $gradeable->useTAGrading()) {
                    $regrade_button .= $this->getProgressBar($TA_percent);
                }
            } else {
                $regrade_button = <<<HTML
                <a class="btn {$button_type_grading} btn-nav" style="width:100%;" disabled>
                    {$button_title} {$date_text}
                </a>
HTML;
            }
        } else if ($gradeable->getType() == GradeableType::CHECKPOINTS) {
            $regrade_button = <<<HTML
                <a class="btn {$button_type_grading} btn-nav"
                href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'simple', 'action' => 'lab', 'g_id' => $gradeable->getId()))}">
                {$button_title} {$date_text}</a>
HTML;
        } elseif ($gradeable->getType() == GradeableType::NUMERIC_TEXT) {
            $regrade_button = <<<HTML
                <a class="btn {$button_type_grading} btn-nav"
                href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'simple', 'action' => 'numeric', 'g_id' => $gradeable->getId()))}">
                {$button_title} {$date_text}</a>
HTML;
        }

        return $regrade_button;
    }

    /**
     * @param Gradeable $gradeable
     * @return string
     */
    private function getEditButton(Gradeable $gradeable): string {
        $admin_button = <<<HTML
                <a class="btn btn-default" style="width:100%;"
                href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'edit_gradeable_page', 'id' => $gradeable->getId()))}">
                    Edit
                </a>
HTML;
        return $admin_button;
    }

    /**
     * @param Gradeable $gradeable
     * @return string
     */
    private function getRebuildButton(Gradeable $gradeable): string {
        $admin_rebuild_button = <<<HTML
                <a class="btn btn-default" style="width:100%;" href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'rebuild_assignement', 'id' => $gradeable->getId()))}">
                    Rebuild
                </a>
HTML;
        return $admin_rebuild_button;
    }

    /**
     * @param Gradeable $gradeable
     * @param string $list_section
     * @return string
     */
    private function getQuickLinkButton(Gradeable $gradeable, string $list_section): string {
        if ($list_section === self::ITEMS_BEING_GRADED) {
            $quick_links = <<<HTML
                        <a class="btn btn-primary" style="width:100%;" href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'quick_link', 'id' => $gradeable->getId(), 'quick_link_action' => 'release_grades_now'))}">
                        RELEASE GRADES NOW
                        </a>
HTML;
        } else if ($list_section === self::FUTURE) {
            $quick_links = <<<HTML
                        <a class="btn btn-primary" style="width:100%;" href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'quick_link', 'id' => $gradeable->getId(), 'quick_link_action' => 'open_ta_now'))}">
                        OPEN TO TAS NOW
                        </a>
HTML;
        } else if ($list_section === self::BETA) {
            if ($gradeable->getType() == GradeableType::ELECTRONIC_FILE) {
                $quick_links = <<<HTML
                        <a class="btn btn-primary" style="width:100%;" href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'quick_link', 'id' => $gradeable->getId(), 'quick_link_action' => 'open_students_now'))}">
                        OPEN NOW
                        </a>
HTML;
            } else {
                $quick_links = <<<HTML
                        <a class="btn btn-primary" style="width:100%;" href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'quick_link', 'id' => $gradeable->getId(), 'quick_link_action' => 'open_grading_now'))}">
                        OPEN TO GRADING NOW
                        </a>
HTML;
            }
        } else if ($list_section === self::CLOSED) {
            $quick_links = <<<HTML
                        <a class="btn btn-primary" style="width:100%;" href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'quick_link', 'id' => $gradeable->getId(), 'quick_link_action' => 'open_grading_now'))}">
                        OPEN TO GRADING NOW
                        </a>
HTML;
        } else {
            $quick_links = "";
        }
        return $quick_links;
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

    public function deleteGradeableForm() {
        return $this->core->getOutput()->renderTwigTemplate("navigation/DeleteGradeableForm.twig");
    }

}
