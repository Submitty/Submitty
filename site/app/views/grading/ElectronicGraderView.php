<?php

namespace app\views\grading;

use app\models\Gradeable;
use app\models\gradeable\AutoGradedGradeable;
use app\models\gradeable\AutoGradedVersion;
use app\models\gradeable\GradedGradeable;
use app\models\SimpleStat;
use app\models\Team;
use app\models\User;
use app\views\AbstractView;

class ElectronicGraderView extends AbstractView {
    /**
     * @param Gradeable $gradeable
     * @param array[] $sections
     * @param SimpleStat[] $component_averages
     * @param SimpleStat|null $autograded_average
     * @param SimpleStat|null $overall_average
     * @param int $total_submissions
     * @param int $registered_but_not_rotating
     * @param int $rotating_but_not_registered
     * @param int $viewed_grade
     * @param string $section_type
     * @param int $regrade_requests
     * @param bool $show_warnings
     * @return string
     */
    public function statusPage(
        Gradeable $gradeable,
        array $sections,
        array $component_averages,
        $autograded_average,
        $overall_average,
        int $total_submissions,
        int $registered_but_not_rotating,
        int $rotating_but_not_registered,
        int $viewed_grade,
        string $section_type,
        int $regrade_requests,
        bool $show_warnings) {

        $peer = false;
        if($gradeable->getPeerGrading() && $this->core->getUser()->getGroup() == User::GROUP_STUDENT) {
            $peer = true;
        }
        $graded = 0;
        $total = 0;
        $no_team_total = 0;
        $team_total = 0;
        $team_percentage = 0;
        $total_students = 0;
        $graded_total = 0;
        $submitted_total = 0;
        $submitted_percentage = 0;
        $peer_total = 0;
        $peer_graded = 0;
        $peer_percentage = 0;
        $viewed_total = 0;
        $viewed_percent = 0;
        $overall_total = 0;
        $overall_percentage = 0;
        $autograded_percentage = 0;
        $component_percentages = [];
        $component_overall_score = 0;
        $component_overall_max = 0;
        $component_overall_percentage = 0;

        foreach ($sections as $key => $section) {
            if ($key === "NULL") {
                continue;
            }
            $graded += $section['graded_components'];
            $total += $section['total_components'];
            if ($gradeable->isTeamAssignment()) {
               $no_team_total += $section['no_team'];
               $team_total += $section['team'];
            }
        }
        if ($total === 0 && $no_team_total === 0){
            $graded_percentage = -1;
        } else if ($total === 0 && $no_team_total > 0){
            $graded_percentage = 0;
        } else{
            $graded_percentage = number_format(($graded / $total) * 100, 1);
        }

        if($graded_percentage !== -1){
            if ($gradeable->isTeamAssignment()) {
                $total_students = $team_total + $no_team_total;
            } else {
                $total_students = $total_submissions;
            }
            $num_components = $gradeable->getNumTAComponents();
            $submitted_total = $total/$num_components;
            $graded_total = round($graded/$num_components, 2);
            if($peer) {
                $num_components = $gradeable->getNumPeerComponents() * $gradeable->getPeerGradeSet();
                $graded_total = $graded/$num_components;
                $submitted_total = $total/$num_components;
            }
            if($total_submissions!=0){
                $submitted_percentage = round(($submitted_total / $total_submissions) * 100, 1);
            }
            //Add warnings to the warnings array to display them to the instructor.
            $warnings = array();
            if($section_type === "rotating_section" && $show_warnings){
                if ($registered_but_not_rotating > 0){
                    array_push($warnings, "There are ".$registered_but_not_rotating." registered students without a rotating section.");
                }
                if($rotating_but_not_registered > 0){
                    array_push($warnings, "There are ".$rotating_but_not_registered." unregistered students with a rotating section.");
                }
            }

            if($gradeable->isTeamAssignment()){
                $team_percentage = round(($team_total/$total_students) * 100, 1);
            }
            if ($peer) {
                $peer_total = floor($sections['stu_grad']['total_components']/$gradeable->getNumPeerComponents());
                $peer_graded = floor($sections['stu_grad']['graded_components']/$gradeable->getNumPeerComponents());
                $peer_percentage = number_format(($sections['stu_grad']['graded_components']/$sections['stu_grad']['total_components']) * 100, 1);
            } else {
                foreach ($sections as $key => &$section) {
                    if ($section['total_components'] == 0) {
                        $section['percentage'] = 0;
                    } else {
                        $section['percentage'] = number_format(($section['graded_components'] / $section['total_components']) * 100, 1);
                    }
                    $section['graded'] = round($section['graded_components']/$num_components, 1);
                    $section['total'] = $section['total_components']/$num_components;

                }
                unset($section); // Clean up reference

                if ($gradeable->taGradesReleased()) {
                    $viewed_total = $total/$num_components;
                    $viewed_percent = number_format(($viewed_grade / max($viewed_total, 1)) * 100, 1);
                }
            }
            if(!$peer) {
                if ($overall_average !== null) {
                    if ($gradeable->getTotalAutograderNonExtraCreditPoints() == null) {
                        $overall_total = $overall_average->getMaxValue();
                    } else {
                        $overall_total = $overall_average->getMaxValue() + $gradeable->getTotalAutograderNonExtraCreditPoints();
                    }
                    if ($overall_total != 0) {
                        $overall_percentage = round($overall_average->getAverageScore() / $overall_total * 100);
                    }
                }
                if ($autograded_average !== null) {
                    if ($gradeable->getTotalAutograderNonExtraCreditPoints() !== 0 && $autograded_average->getCount() !== 0) {
                        $autograded_percentage = round($autograded_average->getAverageScore() / $gradeable->getTotalAutograderNonExtraCreditPoints() * 100);
                    }
                }
                if (count($component_averages) !== 0) {
                    foreach ($component_averages as $comp) {
                        /* @var SimpleStat $comp */
                        $component_overall_score += $comp->getAverageScore();
                        $component_overall_max += $comp->getMaxValue();
                        $percentage = 0;
                        if ($comp->getMaxValue() != 0) {
                            $percentage = round($comp->getAverageScore() / $comp->getMaxValue() * 100);
                        }
                        $component_percentages[] = $percentage;
                    }
                    if ($component_overall_max != 0) {
                        $component_overall_percentage = round($component_overall_score / $component_overall_max * 100);
                    }
                }
                //This else encompasses the above calculations for Teamss
                //END OF ELSE
            }
        }

        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/Status.twig", [
            "gradeable" => $gradeable,
            "peer" => $peer,
            "team_total" => $team_total,
            "team_percentage" => $team_percentage,
            "total_students" => $total_students,
            "total_submissions" => $total_submissions,
            "submitted_total" => $submitted_total,
            "submitted_percentage" => $submitted_percentage,
            "graded_total" => $graded_total,
            "graded_percentage" => $graded_percentage,
            "peer_total" => $peer_total,
            "peer_graded" => $peer_graded,
            "peer_percentage" => $peer_percentage,
            "sections" => $sections,
            "viewed_grade" => $viewed_grade,
            "viewed_total" => $viewed_total,
            "viewed_percent" => $viewed_percent,
            "overall_average" => $overall_average,
            "overall_total" => $overall_total,
            "overall_percentage" => $overall_percentage,
            "autograded_percentage" => $autograded_percentage,
            "autograded_average" => $autograded_average,
            "component_averages" => $component_averages,
            "component_percentages" => $component_percentages,
            "component_overall_score" => $component_overall_score,
            "component_overall_max" => $component_overall_max,
            "component_overall_percentage" => $component_overall_percentage,
            "regrade_requests" => $regrade_requests
        ]);
    }

    /**
     * @param Gradeable $gradeable
     * @param Gradeable[] $rows
     * @param array $graders
     * @param Team[] $empty_teams
     * @param bool $show_all_sections_button
     * @param bool $show_import_teams_button
     * @param bool $show_export_teams_button
     * @param bool $show_edit_teams
     * @return string
     */
    public function detailsPage(Gradeable $gradeable, $rows, $graders, $empty_teams, $show_all_sections_button, $show_import_teams_button, $show_export_teams_button, $show_edit_teams) {
        // Default is viewing your sections
        // Limited grader does not have "View All" option
        // If nothing to grade, Instructor will see all sections
        $view_all = isset($_GET['view']) && $_GET['view'] === 'all';

        $peer = false;
        if ($gradeable->getPeerGrading() && $this->core->getUser()->getGroup() == User::GROUP_STUDENT) {
            $peer = true;
        }

        //Each table column is represented as an array with the following entries:
        // width => how wide the column should be on the page, <td width=X>
        // title => displayed title in the table header
        // function => maps to a macro in Details.twig:render_student
        $columns = [];
        if($peer) {
            $columns[]         = ["width" => "5%",  "title" => "",                 "function" => "index"];
            $columns[]         = ["width" => "30%", "title" => "Student",          "function" => "user_id_anon"];

            if ($gradeable->getTotalNonHiddenNonExtraCreditPoints() !== 0) {
                $columns[]     = ["width" => "15%", "title" => "Autograding",      "function" => "autograding_peer"];
                $columns[]     = ["width" => "20%", "title" => "Grading",          "function" => "grading"];
                $columns[]     = ["width" => "15%", "title" => "Total",            "function" => "total_peer"];
                $columns[]     = ["width" => "15%", "title" => "Active Version",   "function" => "active_version"];
            } else {
                $columns[]     = ["width" => "30%", "title" => "Grading",          "function" => "grading"];
                $columns[]     = ["width" => "20%", "title" => "Total",            "function" => "total_peer"];
                $columns[]     = ["width" => "15%", "title" => "Active Version",   "function" => "active_version"];
            }
        } else {
            if ($gradeable->isTeamAssignment()) {
                if ($show_edit_teams) {
                    $columns[] = ["width" => "3%",  "title" => "",                 "function" => "index"];
                    $columns[] = ["width" => "5%",  "title" => "Section",          "function" => "section"];
                    $columns[] = ["width" => "6%",  "title" => "Edit Teams",       "function" => "team_edit"];
                    $columns[] = ["width" => "12%", "title" => "Team Id",          "function" => "team_id"];
                    $columns[] = ["width" => "32%", "title" => "Team Members",     "function" => "team_members"];
                } else {
                    $columns[] = ["width" => "3%",  "title" => "",                 "function" => "index"];
                    $columns[] = ["width" => "5%",  "title" => "Section",          "function" => "section"];
                    $columns[] = ["width" => "50%", "title" => "Team Members",     "function" => "team_members"];
                }
            } else {
                $columns[]     = ["width" => "3%",  "title" => "",                 "function" => "index"];
                $columns[]     = ["width" => "5%",  "title" => "Section",          "function" => "section"];
                $columns[]     = ["width" => "20%", "title" => "User ID",          "function" => "user_id"];
                $columns[]     = ["width" => "15%", "title" => "First Name",       "function" => "user_first"];
                $columns[]     = ["width" => "15%", "title" => "Last Name",        "function" => "user_last"];
            }
            if ($gradeable->getTotalAutograderNonExtraCreditPoints() !== 0) {
                $columns[]     = ["width" => "9%",  "title" => "Autograding",      "function" => "autograding"];
                if($gradeable->useTAGrading()) {
                    $columns[]     = ["width" => "8%",  "title" => "Graded Questions", "function" => "graded_questions"];
                }
                $columns[]     = ["width" => "8%",  "title" => "TA Grading",       "function" => "grading"];
                $columns[]     = ["width" => "7%",  "title" => "Total",            "function" => "total"];
                $columns[]     = ["width" => "10%", "title" => "Active Version",   "function" => "active_version"];
                if ($gradeable->taGradesReleased()) {
                    $columns[] = ["width" => "8%",  "title" => "Viewed Grade",     "function" => "viewed_grade"];
                }
            } else {
                if($gradeable->useTAGrading()) {
                    $columns[]     = ["width" => "8%",  "title" => "Graded Questions", "function" => "graded_questions"];
                }
                $columns[]     = ["width" => "12%", "title" => "TA Grading",       "function" => "grading"];
                $columns[]     = ["width" => "12%", "title" => "Total",            "function" => "total"];
                $columns[]     = ["width" => "10%", "title" => "Active Version",   "function" => "active_version"];
                if ($gradeable->taGradesReleased()) {
                    $columns[] = ["width" => "8%",  "title" => "Viewed Grade",     "function" => "viewed_grade"];
                }
            }
        }

        //Convert rows into sections and prepare extra row info for things that
        // are too messy to calculate in the template.
        $sections = [];
        foreach ($rows as $row) {
            //Extra info for the template
            $info = [
                "gradeable" => $row
            ];

            if ($peer) {
                $section_title = "PEER STUDENT GRADER";
            } else if ($row->isGradeByRegistration()) {
                $section_title = $row->getTeam() === null ? $row->getUser()->getRegistrationSection() : $row->getTeam()->getRegistrationSection();
            } else {
                $section_title = $row->getTeam() === null ? $row->getUser()->getRotatingSection() : $row->getTeam()->getRotatingSection();
            }
            if ($section_title === null) {
                $section_title = "NULL";
            }

            if (isset($graders[$section_title]) && count($graders[$section_title]) > 0) {
                $section_graders = implode(", ", array_map(function (User $user) {
                    return $user->getId();
                }, $graders[$section_title]));
            } else {
                $section_graders = "Nobody";
            }
            if ($peer) {
                $section_graders = $this->core->getUser()->getId();
            }

            //Team edit button, specifically the onclick event.
            if ($row->isTeamAssignment()) {
                if ($row->getTeam() === null) {
                    $reg_section = ($row->getUser()->getRegistrationSection() === null) ? "NULL" : $row->getUser()->getRegistrationSection();
                    $rot_section = ($row->getUser()->getRotatingSection() === null) ? "NULL" : $row->getUser()->getRotatingSection();
                    $info["team_edit_onclick"] = "adminTeamForm(true, '{$row->getUser()->getId()}', '{$reg_section}', '{$rot_section}', [], [], {$gradeable->getMaxTeamSize()});";
                } else {
                    $user_assignment_setting_json = json_encode($row->getTeam()->getAssignmentSettings($gradeable));
                    $members = json_encode($row->getTeam()->getMembers());
                    $reg_section = ($row->getTeam()->getRegistrationSection() === null) ? "NULL" : $row->getTeam()->getRegistrationSection();
                    $rot_section = ($row->getTeam()->getRotatingSection() === null) ? "NULL" : $row->getTeam()->getRotatingSection();

                    $info["team_edit_onclick"] = "adminTeamForm(false, '{$row->getTeam()->getId()}', '{$reg_section}', '{$rot_section}', {$user_assignment_setting_json}, {$members}, {$gradeable->getMaxTeamSize()});";
                }
            }

            //List of graded components
            $info["graded_groups"] = [];
            foreach ($row->getComponents() as $component) {
                if (is_array($component)) {
                    foreach ($component as $cmpt) {
                        if ($cmpt->getGrader() == null) {
                            $question = $cmpt;
                            break;
                        }
                        if ($cmpt->getGrader()->getId() == $this->core->getUser()->getId()) {
                            $question = $cmpt;
                            break;
                        }
                    }
                    if ($question === null) {
                        $question = $component[0];
                    }
                } else {
                    $question = $component;
                }
                if ($question !== null) {
                    if($question->getGrader() === null) {
                        $info["graded_groups"][] = "NULL";
                    }
                    else {
                        $info["graded_groups"][] = $question->getGrader()->getGroup();
                    }
                }
            }

            //More complicated info generation should go here


            //-----------------------------------------------------------------
            // Now insert this student into the list of sections

            $found = false;
            for ($i = 0; $i < count($sections); $i++) {
                if ($sections[$i]["title"] === $section_title) {
                    $found = true;
                    $sections[$i]["rows"][] = $info;
                    break;
                }
            }
            //Not found? Create it
            if (!$found) {
                $sections[] = ["title" => $section_title, "rows" => [$info], "graders" => $section_graders];
            }
        }

        $empty_team_info = [];
        foreach ($empty_teams as $team) {
            /* @var Team $team */
            $user_assignment_setting_json = json_encode($row->getTeam()->getAssignmentSettings($gradeable));
            $reg_section = ($team->getRegistrationSection() === null) ? "NULL" : $team->getRegistrationSection();
            $rot_section = ($team->getRotatingSection() === null) ? "NULL" : $team->getRotatingSection();

            $empty_team_info[] = [
                "team_edit_onclick" => "adminTeamForm(false, '{$team->getId()}', '{$reg_section}', '{$rot_section}', {$user_assignment_setting_json}, [], {$gradeable->getMaxTeamSize()});"
            ];
        }

        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/Details.twig", [
            "gradeable" => $gradeable,
            "sections" => $sections,
            "graders" => $graders,
            "empty_teams" => $empty_teams,
            "empty_team_info" => $empty_team_info,
            "view_all" => $view_all,
            "show_all_sections_button" => $show_all_sections_button,
            "show_import_teams_button" => $show_import_teams_button,
            "show_export_teams_button" => $show_export_teams_button,
            "columns" => $columns,
            "peer" => $peer
        ]);
    }

    public function adminTeamForm($gradeable, $all_reg_sections, $all_rot_sections) {
        $students = $this->core->getQueries()->getAllUsers();
        $student_full = array();
        foreach ($students as $student) {
            $student_full[] = array('value' => $student->getId(),
                                    'label' => str_replace("'","&#039;",$student->getDisplayedFirstName()).' '.str_replace("'","&#039;",$student->getLastName()).' <'.$student->getId().'>');
        }
        $student_full = json_encode($student_full);

        return $this->core->getOutput()->renderTwigTemplate("grading/AdminTeamForm.twig", [
            "gradeable" => $gradeable,
            "student_full" => $student_full,
            "view" => isset($_REQUEST["view"]) ? $_REQUEST["view"] : null,
            "all_reg_sections" => $all_reg_sections,
            "all_rot_sections" => $all_rot_sections,
        ]);
    }

    public function importTeamForm($gradeable) {
        return $this->core->getOutput()->renderTwigTemplate("grading/ImportTeamForm.twig", [
            "gradeable" => $gradeable
        ]);
    }


    //The student not in section variable indicates that an full access grader is viewing a student that is not in their
    //assigned section. canViewWholeGradeable determines whether hidden testcases can be viewed.
    public function hwGradingPage(Gradeable $gradeable, GradedGradeable $graded_gradeable, int $display_version, float $progress, string $prev_id, string $next_id, bool $not_in_my_section, bool $show_hidden_cases, bool $can_verify, bool $show_verify_all, bool $show_silent_edit) {
        $new_gradeable = $graded_gradeable->getGradeable();

        $peer = false;
        if($this->core->getUser()->getGroup()==User::GROUP_STUDENT && $gradeable->getPeerGrading()) {
            $peer = true;
        }

        $return = "";
        $return .= $this->core->getOutput()->renderTemplate(array('grading', 'ElectronicGrader'), 'renderNavigationBar', $graded_gradeable, $progress, $prev_id, $next_id, $not_in_my_section, $peer);
        $return .= $this->core->getOutput()->renderTemplate(array('grading', 'ElectronicGrader'), 'renderAutogradingPanel', $graded_gradeable->getAutoGradedGradeable()->getAutoGradedVersionInstance($display_version), $show_hidden_cases);
        $return .= $this->core->getOutput()->renderTemplate(array('grading', 'ElectronicGrader'), 'renderSubmissionPanel', $gradeable);
        $user = $gradeable->getUser();
        //If TA grading isn't enabled, the rubric won't actually show up, but the template should be rendered anyway to prevent errors, as the code references the rubric panel
        $return .= $this->core->getOutput()->renderTemplate(array('grading', 'ElectronicGrader'), 'renderRubricPanel', $graded_gradeable, $display_version, $can_verify, $show_verify_all, $show_silent_edit);
        if(!$peer) {
            $return .= $this->core->getOutput()->renderTemplate(array('grading', 'ElectronicGrader'), 'renderInformationPanel', $gradeable, $user);
        }
        if ($graded_gradeable->hasActiveRegradeRequest()) {
            $return .= $this->core->getOutput()->renderTemplate(array('grading', 'ElectronicGrader'), 'renderRegradePanel', $graded_gradeable);
        }
        if ($gradeable->getActiveVersion() == 0) {
            if ($gradeable->hasSubmitted()) {
                $return .= $this->core->getOutput()->renderTwigTemplate("grading/electronic/ErrorMessage.twig", [
                    "color" => "#FF8040", // mango orange
                    "message" => "Cancelled Submission"
                ]);
            } else {
                $return .= $this->core->getOutput()->renderTwigTemplate("grading/electronic/ErrorMessage.twig", [
                    "color" => "#C38189", // lipstick pink (purple)
                    "message" => "No Submission"
                ]);
            }
        } else {
            $status = $gradeable->calculateLateStatus();

            if ($status != "Good" && $status != "Late") {
                $return .= $this->core->getOutput()->renderTwigTemplate("grading/electronic/ErrorMessage.twig", [
                    "color" => "#F62817", // fire engine red
                    "message" => "Late Submission"
                ]);
            }
        }

        return $return;
    }

    /**
     * @param GradedGradeable $graded_gradeable
     * @param float $progress
     * @param string $prev_id
     * @param string $next_id
     * @param bool $not_in_my_section
     * @param bool $peer
     * @return string
     */
    public function renderNavigationBar(GradedGradeable $graded_gradeable, float $progress, string $prev_id, string $next_id, bool $not_in_my_section, bool $peer) {
        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/NavigationBar.twig", [
            "studentNotInSection" => $not_in_my_section,
            "progress" => $progress,
            "peer" => $peer,
            "prev_student_url" => $this->core->buildUrl(['component' => 'grading', 'page' => 'electronic', 'action' => 'grade', 'gradeable_id' => $graded_gradeable->getGradeableId(), 'who_id' => $prev_id]),
            "next_student_url" => $this->core->buildUrl(['component' => 'grading', 'page' => 'electronic', 'action' => 'grade', 'gradeable_id' => $graded_gradeable->getGradeableId(), 'who_id' => $next_id]),
            "home_url" => $this->core->buildUrl(['component' => 'grading', 'page' => 'electronic', 'action' => 'details', 'gradeable_id' => $graded_gradeable->getGradeableId(), 'view' => (count($this->core->getUser()->getGradingRegistrationSections()) == 0) ? 'all' : null]),
            'regrade_panel_available' => $graded_gradeable->hasActiveRegradeRequest(),
        ]);
    }

    /**
     * Render the Auto-Grading Testcases panel
     * @param AutoGradedVersion $version_instance
     * @param bool $show_hidden_cases
     * @return string
     */
    public function renderAutogradingPanel($version_instance, bool $show_hidden_cases) {
        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/AutogradingPanel.twig", [
            "version_instance" => $version_instance,
            "show_hidden_cases" => $show_hidden_cases,
        ]);
    }

    /**
     * Render the Submissions and Results Browser panel
     * @param Gradeable $gradeable
     * @return string
     */
    public function renderSubmissionPanel(Gradeable $gradeable) {
        function add_files(&$files, $new_files, $start_dir_name) {
            $files[$start_dir_name] = array();
            foreach($new_files as $file) {
                $path = explode('/', $file['relative_name']);
                array_pop($path);
                $working_dir = &$files[$start_dir_name];
                foreach($path as $dir) {
                    if (!isset($working_dir[$dir])) {
                        $working_dir[$dir] = array();
                    }
                    $working_dir = &$working_dir[$dir];
                }
                $working_dir[$file['name']] = $file['path'];
            }
        }
        $submissions = array();
        $results = array();
        $checkout = array();

        // NOTE TO FUTURE DEVS: There is code around line 830 (ctrl-f openAll) which depends on these names,
        // if you change here, then change there as well
        // order of these statements matter I believe

        add_files($submissions, array_merge($gradeable->getMetaFiles(), $gradeable->getSubmittedFiles()), 'submissions');

        $vcsFiles = $gradeable->getVcsFiles();
        if( count( $vcsFiles ) != 0 ) { //if there are checkout files, then display folder, otherwise don't
            add_files($checkout, $vcsFiles, 'checkout');
        }

        add_files($results, $gradeable->getResultsFiles(), 'results');

        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/SubmissionPanel.twig", [
            "gradeable" => $gradeable,
            "submissions" => $submissions,
            "checkout" => $checkout,
            "results" => $results,
            "site_url" => $this->core->getConfig()->getSiteUrl()
        ]);
    }

    /**
     * @param Gradeable $gradeable
     * @param User $user
     * @return string
     */
    public function renderInformationPanel(Gradeable $gradeable, User $user) {
        $who = ($gradeable->isTeamAssignment() ? $gradeable->getTeam()->getId() : $gradeable->getUser()->getId());
        $onChange = "versionChange('{$this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'action' => 'grade', 'gradeable_id' => $gradeable->getId(), 'who_id'=>$who, 'gradeable_version' => ""))}', this)";

        $team_members = [];
        if ($gradeable->isTeamAssignment() && $gradeable->getTeam() !== null) {
            foreach ($gradeable->getTeam()->getMembers() as $team_member) {
                $team_members[] = $this->core->getQueries()->getUserById($team_member);
            }
        }

        $tables = [];

        //Late day calculation
        if ($gradeable->isTeamAssignment() && $gradeable->getTeam() !== null) {
            foreach ($gradeable->getTeam()->getMembers() as $team_member) {
                $team_member = $this->core->getQueries()->getUserById($team_member);
                $tables[] = $this->core->getOutput()->renderTemplate('LateDaysTable', 'showLateTable', $team_member->getId(), $gradeable->getId(), false);
            }
        } else {
            $tables[] = $this->core->getOutput()->renderTemplate('LateDaysTable', 'showLateTable', $user->getId(), $gradeable->getId(), false);
        }

        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/StudentInformationPanel.twig", [
            "gradeable" => $gradeable,
            "who" => $who,
            "on_change" => $onChange,
            "team_members" => $team_members,
            "tables" => $tables,
        ]);
    }

    /**
     * Render the Grading Rubric panel
     * @param GradedGradeable $graded_gradeable
     * @return string
     */
    public function renderRubricPanel(GradedGradeable $graded_gradeable, int $display_version, bool $can_verify, bool $show_verify_all, bool $show_silent_edit) {
        $return = "";
        $gradeable = $graded_gradeable->getGradeable();

        // Disable grading if the requested version isn't the active one
        $grading_disabled = $graded_gradeable->getAutoGradedGradeable()->getActiveVersion() == 0
            || $display_version != $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();

        $version_conflict = $graded_gradeable->getAutoGradedGradeable()->getActiveVersion() !== $display_version;
        $has_active_version = $graded_gradeable->getAutoGradedGradeable()->hasActiveVersion();
        $has_submission = $graded_gradeable->getAutoGradedGradeable()->hasSubmission();

        $this->core->getOutput()->addInternalJs('twig.min.js');
        $this->core->getOutput()->addInternalJs('ta-grading-keymap.js');
        $this->core->getOutput()->addInternalJs('ta-grading.js');
        $this->core->getOutput()->addInternalJs('ta-grading-rubric-conflict.js');
        $this->core->getOutput()->addInternalJs('ta-grading-rubric.js');
        $this->core->getOutput()->addInternalJs('gradeable.js');

        $return .= $this->core->getOutput()->renderTwigTemplate("grading/electronic/RubricPanel.twig", [
            "gradeable_id" => $gradeable->getId(),
            "is_ta_grading" => $gradeable->isTaGrading(),
            "anon_id" => $graded_gradeable->getSubmitter()->getAnonId(),
            "show_verify_all" => $show_verify_all,
            "can_verify" => $can_verify,
            "grading_disabled" => $grading_disabled,
            "has_submission" => $has_submission,
            "has_active_version" => $has_active_version,
            "version_conflict" => $version_conflict,
            "show_silent_edit" => $show_silent_edit,
            "grader_id" => $this->core->getUser()->getId(),
            "display_version" => $display_version,
        ]);
        return $return;
    }

    /**
     * Render the Regrade Requests panel
     * @param GradedGradeable $graded_gradeable
     * @return string
     */
    public function renderRegradePanel(GradedGradeable $graded_gradeable) {
        return  $this->core->getOutput()->renderTwigTemplate("grading/electronic/RegradePanel.twig", [
            "graded_gradeable" => $graded_gradeable
        ]);
    }
    
    public function popupStudents() {
        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/ReceivedMarkForm.twig");
    }

    public function popupMarkConflicts() {
        return $this->core->getOutput()->renderTwigTemplate('grading/electronic/MarkConflictPopup.twig');
    }

    public function popupSettings() {
        return $this->core->getOutput()->renderTwigTemplate("grading/SettingsForm.twig");
    }
}
