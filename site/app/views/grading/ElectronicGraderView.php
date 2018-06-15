<?php

namespace app\views\grading;

use app\models\Gradeable;
use app\models\GradeableComponent;
use app\models\SimpleStat;
use app\models\Team;
use app\models\User;
use app\views\AbstractView;
use app\libraries\FileUtils;

class ElectronicGraderView extends AbstractView {
    /**
     * @param Gradeable $gradeable
     * @param array[] $sections
     * @param SimpleStat[] $component_averages
     * @param SimpleStat $autograded_average
     * @param SimpleStat $overall_average
     * @param int $total_submissions
     * @param int $registered_but_not_rotating
     * @param int $rotating_but_not_registered
     * @param int $viewed_grade
     * @param string $section_type
     * @return string
     */
    public function statusPage(
        Gradeable $gradeable,
        array $sections,
        array $component_averages,
        SimpleStat $autograded_average,
        SimpleStat $overall_average,
        int $total_submissions,
        int $registered_but_not_rotating,
        int $rotating_but_not_registered,
        int $viewed_grade,
        string $section_type) {

        $peer = false;
        if($gradeable->getPeerGrading() && $this->core->getUser()->getGroup() == 4) {
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
            if($section_type === "rotating_section" && $this->core->getUser()->accessFullGrading()){
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

                    if ($key === "NULL") {
                        continue;
                    }
                    $valid_graders = array();
                    foreach($section['graders'] as $valid_grader){
                        /* @var User $valid_grader */
                        if($valid_grader->getGroup() <= $gradeable->getMinimumGradingGroup()){
                            $valid_graders[] = $valid_grader->getDisplayedFirstName();
                        }
                    }
                    $section["valid_graders"] = $valid_graders;
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
                if ($gradeable->getTotalAutograderNonExtraCreditPoints() !== 0 && $autograded_average->getCount() !== 0) {
                    $autograded_percentage = round($autograded_average->getAverageScore() / $gradeable->getTotalAutograderNonExtraCreditPoints() * 100);
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
            "component_overall_percentage" => $component_overall_percentage
        ]);
    }

    /**
     * @param Gradeable   $gradeable
     * @param Gradeable[] $rows
     * @param array       $graders
     * @return string
     */
    public function detailsPage(Gradeable $gradeable, $rows, $graders, $all_teams, $empty_teams) {
        // Default is viewing your sections
        // Limited grader does not have "View All" option
        // If nothing to grade, Instructor will see all sections
        $view_all = isset($_GET['view']) && $_GET['view'] === 'all';

        $peer = false;
        if ($gradeable->getPeerGrading() && $this->core->getUser()->getGroup() == 4) {
            $peer = true;
        }
        if ($peer) {
            $grading_count = $gradeable->getPeerGradeSet();
        } else if ($gradeable->isGradeByRegistration()) {
            $grading_count = count($this->core->getUser()->getGradingRegistrationSections());
        } else {
            $grading_count = count($this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable->getId(), $this->core->getUser()->getId()));
        }

        $show_all_sections_button = $this->core->getUser()->accessFullGrading() && (!$this->core->getUser()->accessAdmin() || $grading_count !== 0);
        $show_import_teams_button = $gradeable->isTeamAssignment() && (count($all_teams) > count($empty_teams));
        $show_export_teams_button = $gradeable->isTeamAssignment() && (count($all_teams) == count($empty_teams));

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
                if ($this->core->getUser()->accessAdmin()) {
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
                $columns[]     = ["width" => "8%",  "title" => "Graded Questions", "function" => "graded_questions"];
                $columns[]     = ["width" => "8%",  "title" => "TA Grading",       "function" => "grading"];
                $columns[]     = ["width" => "7%",  "title" => "Total",            "function" => "total"];
                $columns[]     = ["width" => "10%", "title" => "Active Version",   "function" => "active_version"];
                if ($gradeable->taGradesReleased()) {
                    $columns[] = ["width" => "8%",  "title" => "Viewed Grade",     "function" => "viewed_grade"];
                }
            } else {
                $columns[]     = ["width" => "8%",  "title" => "Graded Questions", "function" => "graded_questions"];
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
                    $rot_section = ($row->getUser()->getRotatingSection() === null) ? "NULL" : $row->getUser()->getRegistrationSection();
                    $info["team_edit_onclick"] = "adminTeamForm(true, '{$row->getUser()->getId()}', '{$reg_section}', '{$rot_section}', [], [], {$gradeable->getMaxTeamSize()});";
                } else {
                    $settings_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable->getId(), $row->getTeam()->getId(), "user_assignment_settings.json");
                    $user_assignment_setting = FileUtils::readJsonFile($settings_file);
                    $user_assignment_setting_json = json_encode($user_assignment_setting);
                    $members = json_encode($row->getTeam()->getMembers());
                    $reg_section = ($row->getTeam()->getRegistrationSection() === null) ? "NULL" : $row->getTeam()->getRegistrationSection();
                    $rot_section = ($row->getTeam()->getRotatingSection() === null) ? "NULL" : $row->getTeam()->getRotatingSection();

                    $info["team_edit_onclick"] = "adminTeamForm(false, '{$row->getTeam()->getId()}', '{$reg_section}', '{$rot_section}', {$user_assignment_setting_json}, {$members}, {$gradeable->getMaxTeamSize()});";
                }
            }

            //List of graded components
            $info["graded_components"] = [];
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
                if ($question->getGrader() !== null && $question !== null) {
                    $info["graded_components"][] = $question;
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
            $settings_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable->getId(), $team->getId(), "user_assignment_settings.json");
            $user_assignment_setting = FileUtils::readJsonFile($settings_file);
            $user_assignment_setting_json = json_encode($user_assignment_setting);
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
    public function hwGradingPage(Gradeable $gradeable, float $progress, string $prev_id, string $next_id, $studentNotInSection=false, $canViewWholeGradeable=false) {
        $peer = false;
        if($this->core->getUser()->getGroup()==4 && $gradeable->getPeerGrading()) {
            $peer = true;
        }

        $return = "";

        $return .= $this->core->getOutput()->renderTemplate(array('grading', 'ElectronicGrader'), 'renderNavigationBar', $gradeable, $progress, $prev_id, $next_id, $studentNotInSection, $peer);
        $return .= $this->core->getOutput()->renderTemplate(array('grading', 'ElectronicGrader'), 'renderAutogradingPanel', $gradeable, $canViewWholeGradeable);
        $return .= $this->core->getOutput()->renderTemplate(array('grading', 'ElectronicGrader'), 'renderSubmissionPanel', $gradeable);

        $user = $gradeable->getUser();
        if(!$peer) {
            $return .= $this->core->getOutput()->renderTemplate(array('grading', 'ElectronicGrader'), 'renderInformationPanel', $gradeable, $user);
        }
        if($gradeable->useTAGrading()) {
            $return .= $this->core->getOutput()->renderTemplate(array('grading', 'ElectronicGrader'), 'renderRubricPanel', $gradeable, $user);
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
        }

        return $return;
    }

    /**
     * @param Gradeable $gradeable
     * @param float $progress
     * @param string $prev_id
     * @param string $next_id
     * @param bool $studentNotInSection
     * @param bool $peer
     * @return string
     */
    public function renderNavigationBar(Gradeable $gradeable, float $progress, string $prev_id, string $next_id, bool $studentNotInSection, bool $peer) {
        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/NavigationBar.twig", [
            "studentNotInSection" => $studentNotInSection,
            "prev_id" => $prev_id,
            "next_id" => $next_id,
            "progress" => $progress,
            "gradeable" => $gradeable,
            "peer" => $peer
        ]);
    }

    /**
     * Render the Auto-Grading Testcases panel
     * @param Gradeable $gradeable
     * @param bool $canViewWholeGradeable
     * @return string
     */
    public function renderAutogradingPanel(Gradeable $gradeable, bool $canViewWholeGradeable) {
        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/AutogradingPanel.twig", [
            "gradeable" => $gradeable,
            "canViewWholeGradeable" => $canViewWholeGradeable,
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
            "results" => $results
        ]);
    }

    /**
     * @param Gradeable $gradeable
     * @param User $user
     * @return string
     */
    public function renderInformationPanel(Gradeable $gradeable, User $user) {
        $return = <<<HTML

<div id="student_info" class="draggable rubric_panel" style="right:15px; bottom:40px; width:48%; height:30%;">
    <div class="draggable_content">
    <span class="grading_label">Student Information</span>
    <div class="inner-container">
        <h5 class='label' style="float:right; padding-right:15px;">Browse Student Submissions:</h5>
        <div class="rubric-title">
HTML;
            $who = ($gradeable->isTeamAssignment() ? $gradeable->getTeam()->getId() : $gradeable->getUser()->getId());
            $onChange = "versionChange('{$this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'action' => 'grade', 'gradeable_id' => $gradeable->getId(), 'who_id'=>$who, 'gradeable_version' => ""))}', this)";
            $formatting = "font-size: 13px;";
            $return .= <<<HTML
            <div style="float:right;">
HTML;
        $return .= $this->core->getOutput()->renderTemplate('AutoGrading', 'showVersionChoice', $gradeable, $onChange, $formatting);

        // If viewing the active version, show cancel button, otherwise show button to switch active
        if ($gradeable->getCurrentVersionNumber() > 0) {
            if ($gradeable->getCurrentVersionNumber() == $gradeable->getActiveVersion()) {
                $version = 0;
                $button = '<input type="submit" class="btn btn-default btn-xs" style="float:right; margin: 0 10px;" value="Cancel Student Submission">';
            } else {
                $version = $gradeable->getCurrentVersionNumber();
                $button = '<input type="submit" class="btn btn-default btn-xs" style="float:right; margin: 0 10px;" value="Grade This Version">';
            }
            $return .= <<<HTML
                <br/><br/>
                <form style="display: inline;" method="post" onsubmit='return checkTaVersionChange();'
                        action="{$this->core->buildUrl(array('component' => 'student',
                'action' => 'update',
                'gradeable_id' => $gradeable->getId(),
                'new_version' => $version, 'ta' => true, 'who' => $who))}">
                    <input type='hidden' name="csrf_token" value="{$this->core->getCsrfToken()}" />
                    {$button}
                </form>
HTML;
        }
        $return .= <<<HTML
            </div>
            <div>
HTML;

        if ($gradeable->isTeamAssignment() && $gradeable->getTeam() !== null) {
            $return .= <<<HTML
                <b>Team:<br/>
HTML;
            foreach ($gradeable->getTeam()->getMembers() as $team_member) {
                $team_member = $this->core->getQueries()->getUserById($team_member);
                $return .= <<<HTML
                &emsp;{$team_member->getDisplayedFirstName()} {$team_member->getLastName()} ({$team_member->getId()})<br/>
HTML;
            }
        } else {
            $return .= <<<HTML
                <b>{$user->getDisplayedFirstName()} {$user->getLastName()} ({$user->getId()})<br/>
HTML;
        }

        $return .= <<<HTML
                Submission Number: {$gradeable->getActiveVersion()} / {$gradeable->getHighestVersion()}<br/>
                Submitted: {$gradeable->getSubmissionTime()->format("m/d/Y H:i:s")}<br/></b>
            </div>
HTML;
        $return .= <<<HTML
            <form id="rubric_form">
                <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
                <input type="hidden" name="g_id" value="{$gradeable->getId()}" />
                <input type="hidden" name="u_id" value="{$user->getId()}" />
                <input type="hidden" name="graded_version" value="{$gradeable->getActiveVersion()}" />
HTML;

        //Late day calculation
        $color = "green";
        $status = "Good";
        if ($gradeable->isTeamAssignment() && $gradeable->getTeam() !== null) {
            $team_status = "Bad for all team members";
            foreach ($gradeable->getTeam()->getMembers() as $team_member) {
                $team_member = $this->core->getQueries()->getUserById($team_member);
                $return .= $this->makeTable($team_member->getId(), $gradeable, $status);
                if($status == "Good" || $status == "Late"){
                    // As long as one person on the team has a good status, then the assignment should be graded.
                    $team_status = "Good";
                }
            }
            $status = $team_status;
        } else {
            $return .= $this->makeTable($user->getId(), $gradeable, $status);
        }
        if ($status != "Good" && $status != "Late" && $status != "No submission") {
            $color = "red";
            $my_color = "'#F62817'"; // fire engine red
            $my_message = "Late Submission";
            $return .= <<<HTML
                <script>
                    $('body').css('background', $my_color);
                    $('#bar_wrapper').append("<div id='bar_banner' class='banner'>$my_message</div>");
                    $('#bar_banner').css('background-color', $my_color);
                    $('#bar_banner').css('color', 'black');
                </script>
                <b>Status:</b> <span style="color:{$color};">{$status}</span><br />
HTML;
        }

        $return .= <<<HTML
        </div>
    </div>
    </div>
</div>
HTML;
        return $return;
    }

    /**
     * Render the Grading Rubric panel
     * @param Gradeable $gradeable
     * @param User $user
     * @return string
     */
    public function renderRubricPanel(Gradeable $gradeable, User $user) {
        $return = "";

        $display_verify_all = false;
        //check if verify all button should be shown or not
        foreach ($gradeable->getComponents() as $component) {
            if (!$component->getGrader()) {
                continue;
            }
            if ($component->getGrader()->getId() !== $this->core->getUser()->getId() && $this->core->getUser()->accessFullGrading()) {
                $display_verify_all = true;
                break;
            }
        }
        $disabled = $gradeable->getActiveVersion() == 0 || $gradeable->getCurrentVersionNumber() != $gradeable->getActiveVersion();

        // if use student components, get the values for pages from the student's submissions
        $files = $gradeable->getSubmittedFiles();
        $student_pages = array();
        foreach ($files as $filename => $content) {
            if ($filename == "student_pages.json") {
                $path = $content["path"];
                $student_pages = FileUtils::readJsonFile($content["path"]);
            }
        }

        $grading_data = [
            "gradeable" => $gradeable->getGradedData(),
            "your_user_id" => $this->core->getUser()->getId(),
            "disabled" => $disabled,
            "can_verify" => $display_verify_all // If any can be then this is set
        ];

        foreach ($grading_data["gradeable"]["components"] as &$component) {
            $page = intval($component["page"]);
            // if the page is determined by the student json
            if ($page == -1) {
                // usually the order matches the json
                if ($student_pages[intval($component["order"])]["order"] == intval($component["order"])) {
                    $page = intval($student_pages[intval($component["order"])]["page #"]);
                } // otherwise, iterate through until the order matches
                else {
                    foreach ($student_pages as $student_page) {
                        if ($student_page["order"] == intval($component["order"])) {
                            $page = intval($student_page["page #"]);
                            $component["page"] = $page;
                            break;
                        }
                    }
                }
            }
        }
        //References need to be cleaned up
        unset($component);

        $grading_data = json_encode($grading_data, JSON_PRETTY_PRINT);

        $this->core->getOutput()->addInternalJs('twig.min.js');
        $this->core->getOutput()->addInternalJs('ta-grading-keymap.js');
        $this->core->getOutput()->addInternalJs('ta-grading.js');
        $this->core->getOutput()->addInternalJs('ta-grading-mark.js');
        $this->core->getOutput()->addInternalJs('gradeable.js');

        $return .= $this->core->getOutput()->renderTwigTemplate("grading/electronic/RubricPanel.twig", [
            "gradeable" => $gradeable,
            "display_verify_all" => $display_verify_all,
            "user" => $user,
            "grading_data" => $grading_data
        ]);
        return $return;
    }

    public function popupStudents() {
        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/ReceivedMarkForm.twig");
    }

    public function popupNewMark() {
        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/NewMarkForm.twig");
    }

    public function popupSettings() {
        return $this->core->getOutput()->renderTwigTemplate("grading/SettingsForm.twig");
    }

    private function makeTable($user_id, $gradeable, &$status){
        $return = <<<HTML
        <h3>Overall Late Day Usage for {$user_id}</h3><br/>
        <table>
            <thead>
                <tr>
                    <th></th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Allowed per term</th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Allowed per assignment</th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Submitted days after deadline</th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Extensions</th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Status</th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Late Days Charged</th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Total Late Days Used</th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Remaining Days</th>
                </tr>
            </thead>
            <tbody>
HTML;
        $total_late_used = 0;
        $order_by = [ 
            'CASE WHEN eg.eg_submission_due_date IS NOT NULL THEN eg.eg_submission_due_date ELSE g.g_grade_released_date END' 
        ];
        foreach ($this->core->getQueries()->getGradeablesIterator(null, $user_id, 'registration_section', 'u.user_id', 0, $order_by) as $g) {
            $g->calculateLateDays($total_late_used);
            $class = "";
            if($g->getId() == $gradeable->getId()){
                $class = "class='yellow-background'";
                $status = $g->getLateStatus();
            }
            $remaining = max(0, $g->getStudentAllowedLateDays() - $total_late_used);
            $return .= <<<HTML
                <tr>
                    <th $class style="padding:5px; border:thin solid black">{$g->getName()}</th>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$g->getStudentAllowedLateDays()}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$g->getAllowedLateDays()}</td> 
                    <td $class align="center" style="padding:5px; border:thin solid black">{$g->getLateDays()}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$g->getLateDayExceptions()}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$g->getLateStatus()}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$g->getCurrLateCharged()}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$total_late_used}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$remaining}</td>
                </tr>
HTML;
        }
        $return .= <<<HTML
            </tbody>
        </table>
HTML;
        return $return;
    }
}
