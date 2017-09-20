<?php

namespace app\controllers\grading;

use app\controllers\AbstractController;
use app\models\User;
use app\models\HWReport;
use \app\libraries\GradeableType;
use app\models\Gradeable;
use app\models\GradeableComponent;
use app\models\GradeableComponentMark;
use app\libraries\FileUtils;

class ElectronicGraderController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'details':
                $this->showDetails();
                break;
            case 'submit_team_form':
                $this->adminTeamSubmit();
                break;
            case 'grade':
                $this->showGrading();
                break;
            case 'save_one_component':
                $this->saveSingleComponent();
                break;
            case 'save_gradeable_comment':
                $this->saveGradeableComment();
                break;
            case 'get_mark_data':
                $this->getMarkDetails();
                break;
            case 'get_gradeable_comment':
                $this->getGradeableComment();
                break;
            case 'get_marked_users':
                $this->getUsersThatGotTheMark();
                break;
            default:
                $this->showStatus();
                break;
        }
    }

    /**
     * Shows statistics for the grading status of a given electronic submission. This is shown to all full access
     * graders. Limited access graders will only see statistics for the sections they are assigned to.
     */
    public function showStatus() {
        $gradeable_id = $_REQUEST['gradeable_id'];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id);
        $this->core->getOutput()->addBreadcrumb("Status {$gradeable->getName()}");
        $peer = false;
        if ($this->core->getUser()->getGroup() > $gradeable->getMinimumGradingGroup()) {
            if ($gradeable->getPeerGrading() && ($this->core->getUser()->getGroup() == 4)) {
                $peer = true;
            }
            else {
                $this->core->addErrorMessage("You do not have permission to grade {$gradeable->getName()}");
                $this->core->redirect($this->core->getConfig()->getSiteUrl());
            }
        }

        /*
         * we need number of students per section
         */

        $no_team_users = array();
        $graded_components = array();
        $graders = array();
        $average_scores = array();
        $sections = array();
        $total_users = array();
        if ($peer) {
            $peer_grade_set = $gradeable->getPeerGradeSet();
            $total_users = $this->core->getQueries()->getTotalUserCountByGradingSections($sections, 'registration_section');
            $num_components = $gradeable->getNumPeerComponents();
            $graded_components = $this->core->getQueries()->getGradedPeerComponentsByRegistrationSection($gradeable_id, $sections);
            $my_grading = $this->core->getQueries()->getNumGradedPeerComponents($gradeable->getId(), $this->core->getUser()->getId());
            $component_averages = array();
            $overall_average = array();
        }
        else if ($gradeable->isGradeByRegistration()) {
            if(!$this->core->getUser()->accessFullGrading()){
                $sections = $this->core->getUser()->getGradingRegistrationSections();
            }
            else {
                $sections = $this->core->getQueries()->getRegistrationSections();
                foreach ($sections as $i => $section) {
                    $sections[$i] = $section['sections_registration_id'];
                }
            }
            $section_key='registration_section';
            if (count($sections) > 0) {
                $graders = $this->core->getQueries()->getGradersForRegistrationSections($sections);
            }
            $num_components = $gradeable->getNumTAComponents();
        }
        else {
            if(!$this->core->getUser()->accessFullGrading()){
                $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id, $this->core->getUser()->getId());
            }
            else {
                $sections = $this->core->getQueries()->getRotatingSections();
                foreach ($sections as $i => $section) {
                    $sections[$i] = $section['sections_rotating_id'];
                }
            }
            $section_key='rotating_section';
            if (count($sections) > 0) {
                $graders = $this->core->getQueries()->getGradersForRotatingSections($gradeable_id, $sections);
            }
        }

        if (count($sections) > 0) {
            if ($gradeable->isTeamAssignment()) {
                $total_users = $this->core->getQueries()->getTotalTeamCountByGradingSections($gradeable_id, $sections, $section_key);
                $no_team_users = $this->core->getQueries()->getUsersWithoutTeamByGradingSections($gradeable_id, $sections, $section_key);
                $graded_components = $this->core->getQueries()->getGradedComponentsCountByTeamGradingSections($gradeable_id, $sections, $section_key);
            }
            else {
                $total_users = $this->core->getQueries()->getTotalUserCountByGradingSections($sections, $section_key);
                $no_team_users = array();
                $graded_components = $this->core->getQueries()->getGradedComponentsCountByGradingSections($gradeable_id, $sections, $section_key);
                $component_averages = $this->core->getQueries()->getAverageComponentScores($gradeable_id);
                $overall_average = $this->core->getQueries()->getAverageForGradeable($gradeable_id);
            }
            $num_components = $gradeable->getNumTAComponents();
        }
        $sections = array();
        if (count($total_users) > 0) {
            if ($peer) {
                $sections['stu_grad'] = array(
                    'total_components' => $num_components * $peer_grade_set,
                    'graded_components' => $my_grading,
                    'graders' => array()
                );
                $sections['all'] = array(
                    'total_components' => 0,
                    'graded_components' => 0,
                    'graders' => array()
                );
                foreach($total_users as $key => $value) {
                    if($key == 'NULL') continue;
                    $sections['all']['total_components'] += $value *$num_components*$peer_grade_set;
                    $sections['all']['graded_components'] += isset($graded_components[$key]) ? $graded_components[$key] : 0;
                }
                $sections['all']['total_components'] -= $peer_grade_set*$num_components;
                $sections['all']['graded_components'] -= $my_grading;
            }
            else {

                foreach ($total_users as $key => $value) {
                    $sections[$key] = array(
                        'total_components' => $value * $num_components,
                        'graded_components' => 0,
                        'graders' => array()
                    );
                    if (isset($graded_components[$key])) {
                        $sections[$key]['graded_components'] = intval($graded_components[$key]);
                    }
                    if (isset($graders[$key])) {
                        $sections[$key]['graders'] = $graders[$key];
                    }
                    if ($gradeable->isTeamAssignment()) {
                    $sections[$key]['no_team'] = $no_team_users[$key];
                    }
                    if (isset($graded_components[$key])) {
                        $sections[$key]['graded_components'] = intval($graded_components[$key]);
                    }
                }
            }
        }
        $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'statusPage', $gradeable, $sections, $component_averages, $overall_average);
    }

    /**
     * This loads a gradeable and
     */
    public function showDetails() {
        $gradeable_id = $_REQUEST['gradeable_id'];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id);
        $this->core->getOutput()->addBreadcrumb("Details {$gradeable->getName()}");
        $peer = false;
        if ($gradeable === null) {
            $this->core->getOutput()->renderOutput('Error', 'noGradeable', $gradeable_id);
            return;
        }
        if ($this->core->getUser()->getGroup() > $gradeable->getMinimumGradingGroup()) {
            if($gradeable->getPeerGrading() && $this->core->getUser()->getGroup() == 4) {
                $peer = true;
            }
            else {
                $this->core->addErrorMessage("You do not have permission to grade {$gradeable->getName()}");
                $this->core->redirect($this->core->getConfig()->getSiteUrl());
            }
        }

        $students = array();
        if ($peer) {
            $student_ids = $this->core->getQueries()->getPeerAssignment($gradeable->getId(), $this->core->getUser()->getId());
            $graders = array();
            $section_key = "registration_section";
        }
        else if ($gradeable->isGradeByRegistration()) {
            $section_key = "registration_section";
            $sections = $this->core->getUser()->getGradingRegistrationSections();
            if (!isset($_GET['view']) || $_GET['view'] !== "all") {
                $students = $this->core->getQueries()->getUsersByRegistrationSections($sections);
            }
            $graders = $this->core->getQueries()->getGradersForRegistrationSections($sections);
        }
        else {
            $section_key = "rotating_section";
            $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id,
                $this->core->getUser()->getId());
            if (!isset($_GET['view']) || $_GET['view'] !== "all") {
                $students = $this->core->getQueries()->getUsersByRotatingSections($sections);
            }
            $graders = $this->core->getQueries()->getGradersForRotatingSections($gradeable->getId(), $sections);
        }
        if ((isset($_GET['view']) && $_GET['view'] === "all") || ($this->core->getUser()->accessAdmin() && count($sections) === 0)) {
            //Checks to see if the Grader has access to all users in the course,
            //Will only show the sections that they are graders for if not TA or Instructor
            if($this->core->getUser()->getGroup() < 3) {
                $students = $this->core->getQueries()->getAllUsers($section_key);
            } else {
                $students = $this->core->getQueries()->getUsersByRotatingSections($sections);
            }
        }

        if(!$peer) {
            $student_ids = array_map(function(User $student) { return $student->getId(); }, $students);
        }

        $empty_teams = array();
        if ($gradeable->isTeamAssignment()) {
            // Only give getGradeables one User ID per team
            $all_teams = $this->core->getQueries()->getTeamsByGradeableId($gradeable_id);
            foreach($all_teams as $team) {
                $student_ids = array_diff($student_ids, $team->getMembers());
                $team_section = $gradeable->isGradeByRegistration() ? $team->getRegistrationSection() : $team->getRotatingSection();
                if ($team->getSize() > 0 && (in_array($team_section, $sections) ||
                                            (isset($_GET['view']) && $_GET['view'] === "all") ||
                                            (count($sections) === 0 && $this->core->getUser()->accessAdmin()))) {
                    $student_ids[] = $team->getMembers()[0];
                }
                if ($team->getSize() === 0 && $this->core->getUser()->accessAdmin()) {
                    $empty_teams[] = $team;
                }
            }
        }

        $rows = $this->core->getQueries()->getGradeables($gradeable_id, $student_ids, $section_key);
        if ($gradeable->isTeamAssignment()) {
            // Rearrange gradeables arrray into form (sec 1 teams, sec 1 individuals, sec 2 teams, sec 2 individuals, etc...)
            $sections = array();
            $individual_rows = array();
            $team_rows = array();
            foreach($rows as $row) {
                if ($gradeable->isGradeByRegistration()) {
                    $section = $row->getTeam() === null ? strval($row->getUser()->getRegistrationSection()) : strval($row->getTeam()->getRegistrationSection());
                }
                else {
                    $section = $row->getTeam() === null ? strval($row->getUser()->getRotatingSection()) : strval($row->getTeam()->getRotatingSection());
                }

                if ($section != null && !in_array($section, $sections)) {
                    $sections[] = $section;
                }

                if ($row->getTeam() === null) {
                    if (!isset($individual_rows[$section])) {
                        $individual_rows[$section] = array();
                    }
                    $individual_rows[$section][] = $row;
                }
                else {
                    if (!isset($team_rows[$section])) {
                        $team_rows[$section] = array();
                    }
                    $team_rows[$section][] = $row;
                }
            }

            asort($sections);
            $rows = array();
            foreach($sections as $section) {
                if (isset($team_rows[$section])) {
                    $rows = array_merge($rows, $team_rows[$section]);
                }
                if (isset($individual_rows[$section])) {
                    $rows = array_merge($rows, $individual_rows[$section]);
                }
            }
            // Put null section at end of array
            if (isset($team_rows[""])) {
                $rows = array_merge($rows, $team_rows[""]);
            }
            if (isset($individual_rows[""])) {
                $rows = array_merge($rows, $individual_rows[""]);
            }
        }
        $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'detailsPage', $gradeable, $rows, $graders, $empty_teams);

        if ($gradeable->isTeamAssignment() && $this->core->getUser()->accessAdmin()) {
            if ($gradeable->isGradeByRegistration()) {
                $all_sections = $this->core->getQueries()->getRegistrationSections();
                $key = 'sections_registration_id';
            }
            else {
                $all_sections = $this->core->getQueries()->getRotatingSections();
                $key = 'sections_rotating_id';
            }
            foreach ($all_sections as $i => $section) {
                $all_sections[$i] = $section[$key];
            }
            $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'adminTeamForm', $gradeable, $all_sections);
        }
    }

    public function adminTeamSubmit() {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] != $this->core->getCsrfToken()) {
            $this->core->addErrorMessage("Invalid CSRF Token");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }

        if (!$this->core->getUser()->accessAdmin()) {
            $this->core->addErrorMessage("Only admins can edit teams");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }

        $gradeable_id = $_REQUEST['gradeable_id'];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id);

        $return_url = $this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'details','gradeable_id'=>$gradeable_id));
        if (isset($_POST['view'])) $return_url .= "&view={$_POST['view']}";

        if (!$gradeable->isTeamAssignment()) {
            $this->core->addErrorMessage("{$gradeable->getName()} is not a team assignment");
            $this->core->redirect($return_url);
        }

        $num_users = intval($_POST['num_users']);
        $user_ids = array();
        for ($i = 0; $i < $num_users; $i++) {
            $id = trim(htmlentities($_POST["user_id_{$i}"]));
            if (($id !== "") && !in_array($id, $user_ids)) {
                if ($this->core->getQueries()->getUserById($id) === null) {
                    $this->core->addErrorMessage("ERROR: {$id} is not a valid User ID");
                    $this->core->redirect($return_url);
                }
                $user_ids[] = $id;
            }
        }
        $new_team = $_POST['new_team'] === 'true' ? true : false;

        if ($new_team) {
            $team_leader_id = null;
            foreach($user_ids as $id) {
                if ($this->core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $id) !== null) {
                    $this->core->addErrorMessage("ERROR: {$id} is already on a team");
                    $this->core->redirect($return_url);
                }
                if ($id === $_POST['new_team_user_id']) {
                    $team_leader_id = $id;
                    if ($gradeable->isGradeByRegistration()) {
                        $registration_section = $_POST['section'] === "NULL" ? null : intval($_POST['section']);
                        $rotating_section = $this->core->getQueries()->getUserById($id)->getRotatingSection();
                    }
                    else {
                        $registration_section = $this->core->getQueries()->getUserById($id)->getRegistrationSection();
                        $rotating_section = $_POST['section'] === "NULL" ? null : intval($_POST['section']);
                    }
                }
            }
            if ($team_leader_id === null) {
                $this->core->addErrorMessage("ERROR: {$_POST['new_team_user_id']} must be on the team");
                $this->core->redirect($return_url);
            }

            $team_id = $this->core->getQueries()->createTeam($gradeable_id, $team_leader_id, $registration_section, $rotating_section);
            foreach($user_ids as $id) {
                $this->core->getQueries()->declineAllTeamInvitations($gradeable_id, $id);
                if ($id !== $team_leader_id) $this->core->getQueries()->acceptTeamInvitation($team_id, $id);
            }
            $this->core->addSuccessMessage("Created New Team {$team_id}");

            $gradeable_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable_id);
            if (!FileUtils::createDir($gradeable_path)) {
                $this->core->addErrorMEssage("Failed to make folder for this assignment");
                $this->core->redirect($return_url);
            }

            $user_path = FileUtils::joinPaths($gradeable_path, $team_id);
            if (!FileUtils::createDir($user_path)) {
                $this->core->addErrorMEssage("Failed to make folder for this assignment for the team");
                $this->core->redirect($return_url);
            }

            $current_time = (new \DateTime('now', $this->core->getConfig()->getTimezone()))->format("Y-m-d H:i:sO")." ".$this->core->getConfig()->getTimezone()->getName();
            $settings_file = FileUtils::joinPaths($user_path, "user_assignment_settings.json");
            $json = array("team_history" => array(array("action" => "admin_create", "time" => $current_time,
                                                        "admin_user" => $this->core->getUser()->getId(), "first_user" => $team_leader_id)));
            foreach($user_ids as $id) {
                if ($id !== $team_leader_id) {
                    $json["team_history"][] = array("action" => "admin_add_user", "time" => $current_time,
                                                    "admin_user" => $this->core->getUser()->getId(), "added_user" => $id);
                }
            }
        }
        else {
            $team_id = $_POST['edit_team_team_id'];
            $team = $this->core->getQueries()->getTeamById($team_id);
            if ($team === null) {
                $this->core->addErrorMessage("ERROR: {$team_id} is not a valid Team ID");
                $this->core->redirect($return_url);
            }
            $team_members = $team->getMembers();
            $add_user_ids = array();
            foreach($user_ids as $id) {
                if (!in_array($id, $team_members)) {
                    if ($this->core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $id) !== null) {
                        $this->core->addErrorMessage("ERROR: {$id} is already on a team");
                        $this->core->redirect($return_url);
                    }
                    $add_user_ids[] = $id;
                }
            }
            $remove_user_ids = array();
            foreach($team_members as $id) {
                if (!in_array($id, $user_ids)) {
                    $remove_user_ids[] = $id;
                }
            }

            $section = $_POST['section'] === "NULL" ? null : intval($_POST['section']);
            if ($gradeable->isGradeByRegistration()) {
                $this->core->getQueries()->updateTeamRegistrationSection($team_id, $section);
            }
            else {
                $this->core->getQueries()->updateTeamRotatingSection($team_id, $section);
            }
            foreach($add_user_ids as $id) {
                $this->core->getQueries()->declineAllTeamInvitations($gradeable_id, $id);
                $this->core->getQueries()->acceptTeamInvitation($team_id, $id);
            }
            foreach($remove_user_ids as $id) {
                $this->core->getQueries()->leaveTeam($team_id, $id);
            }
            $this->core->addSuccessMessage("Updated Team {$team_id}");

            $current_time = (new \DateTime('now', $this->core->getConfig()->getTimezone()))->format("Y-m-d H:i:sO")." ".$this->core->getConfig()->getTimezone()->getName();
            $settings_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable_id, $team_id, "user_assignment_settings.json");
            $json = FileUtils::readJsonFile($settings_file);
            if ($json === false) {
                $this->core->addErrorMEssage("Failed to open settings file");
                $this->core->redirect($return_url);
            }
            foreach($add_user_ids as $id) {
                $json["team_history"][] = array("action" => "admin_add_user", "time" => $current_time,
                                                    "admin_user" => $this->core->getUser()->getId(), "added_user" => $id);
            }
            foreach($remove_user_ids as $id) {
                $json["team_history"][] = array("action" => "admin_remove_user", "time" => $current_time,
                                                    "admin_user" => $this->core->getUser()->getId(), "removed_user" => $id);
            }
        }
        if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
            $this->core->addErrorMEssage("Failed to write to team history to settings file");
        }
        $this->core->redirect($return_url);
    }

    public function showGrading() {
        $gradeable_id = $_REQUEST['gradeable_id'];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id);
        $peer = false;
        if ($this->core->getUser()->getGroup() > $gradeable->getMinimumGradingGroup()) {
            if($gradeable->getPeerGrading() && $this->core->getUser()->getGroup()==4) {
                $peer = true;
            }
            else {
                $this->core->addErrorMessage("You do not have permission to grade {$gradeable->getName()}");
                $this->core->redirect($this->core->getConfig()->getSiteUrl());
            }
        }


        $graded = 0;
        $total = 0;
        if($peer) {
            $section_key = 'registration_section';
            $user_ids_to_grade = $this->core->getQueries()->getPeerAssignment($gradeable->getId(), $this->core->getUser()->getId());
            $total = $gradeable->getPeerGradeSet();
            $graded = $this->core->getQueries()->getNumGradedPeerComponents($gradeable->getId(), $this->core->getUser()->getId()) / $gradeable->getNumPeerComponents();
        }
        else if ($gradeable->isGradeByRegistration()) {
            $section_key = "registration_section";
            $sections = $this->core->getUser()->getGradingRegistrationSections();
            if ($this->core->getUser()->accessAdmin() && $sections == null) {
                $sections = $this->core->getQueries()->getRegistrationSections();
                for ($i = 0; $i < count($sections); $i++) {
                    $sections[$i] = $sections[$i]['sections_registration_id'];
                }
            }
            $users_to_grade = $this->core->getQueries()->getUsersByRegistrationSections($sections);
            $total = array_sum($this->core->getQueries()->getTotalUserCountByGradingSections($sections, 'registration_section'));
            $graded = array_sum($this->core->getQueries()->getGradedComponentsCountByGradingSections($gradeable_id, $sections, 'registration_section'));
        }
        else {
            $section_key = "rotating_section";
            $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id, $this->core->getUser()->getId());
            if ($this->core->getUser()->accessAdmin() && $sections == null) {
                $sections = $this->core->getQueries()->getRotatingSections();
                for ($i = 0; $i < count($sections); $i++) {
                    $sections[$i] = $sections[$i]['sections_rotating_id'];
                }
            }
            $users_to_grade = $this->core->getQueries()->getUsersByRotatingSections($sections);
            $total = array_sum($this->core->getQueries()->getTotalUserCountByGradingSections($sections, 'rotating_section'));
            $graded = array_sum($this->core->getQueries()->getGradedComponentsCountByGradingSections($gradeable_id, $sections, 'rotating_section'));
        }

        //multiplies users and the number of components a gradeable has together
        $total = $total * count($gradeable->getComponents());
        if($total == 0) {
            $progress = 100;
        }
        else {
            $progress = round(($graded / $total) * 100, 1);
        }
        if(!$peer) {
            $user_ids_to_grade = array_map(function(User $user) { return $user->getId(); }, $users_to_grade);
        }
        $gradeables_to_grade = $this->core->getQueries()->getGradeables($gradeable_id, $user_ids_to_grade, $section_key);

        $who_id = isset($_REQUEST['who_id']) ? $_REQUEST['who_id'] : "";
        //$who_id = isset($who_id[$_REQUEST['who_id']]) ? $who_id[$_REQUEST['who_id']] : "";
        if (($who_id !== "") && ($this->core->getUser()->getGroup() === 3) && !in_array($who_id, $user_ids_to_grade)) {
            $this->core->addErrorMessage("You do not have permission to grade {$who_id}");
            $this->core->redirect($this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'gradeable_id' => $gradeable_id)));
        }
        if($peer && !in_array($who_id, $user_ids_to_grade)) {
            $_SESSION['messages']['error'][] = "You do not have permission to grade this student.";
            $this->core->redirect($this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'gradeable_id' => $gradeable_id)));
        }

        $prev_id = "";
        $next_id = "";
        $break_next = false;
        foreach ($gradeables_to_grade as $g) {
            $id = $g->getUser()->getId();
            if ($break_next) {
                $next_id = $id;
                break;
            }
            if (($who_id === "" && !$g->beenTAgraded()) || $who_id === $id) {
                $who_id = $id;
                $break_next = true;
            }
            else {
                $prev_id = $id;
            }
        }
        if ($who_id === "") {
            $this->core->addSuccessMessage("Finished grading for {$gradeable->getName()}");
            $this->core->redirect($this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'gradeable_id' => $gradeable_id)));
        }

        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $who_id);
        $gradeable->loadResultDetails();
        $individual = $_REQUEST['individual'];

        $anon_ids = $this->core->getQueries()->getAnonId(array($prev_id, $next_id));

        $this->core->getOutput()->addCSS($this->core->getConfig()->getBaseUrl()."/css/ta-grading.css");
        $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'hwGradingPage', $gradeable, $progress, $prev_id, $next_id, $individual);
        $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'popupStudents');
    }

    public function saveSingleComponent() {
        $grader_id = $this->core->getUser()->getId();
        $gradeable_id = $_POST['gradeable_id'];
        $user_id = $this->core->getQueries()->getUserFromAnon($_POST['anon_id'])[$_POST['anon_id']];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $user_id);
        $overwrite = $_POST['overwrite'];

        if ($this->core->getUser()->getGroup() === 4) {
            if(!$gradeable->getPeerGrading()) {
                $this->core->addErrorMessage("You do not have permission to grade this");
                return;
            }
            else {
                $user_ids_to_grade = $this->core->getQueries()->getPeerAssignment($gradeable->getId(), $this->core->getUser()->getId());
                if(!in_array($user_id, $user_ids_to_grade)) {
                    $this->core->addErrorMessage("You do not have permission to grade this student");
                    return;
                }
            }
        }
        else if ($this->core->getUser()->getGroup() === 3) {
            if ($gradeable->isGradeByRegistration()) {
                $sections = $this->core->getUser()->getGradingRegistrationSections();
                $users_to_grade = $this->core->getQueries()->getUsersByRegistrationSections($sections);
            }
            else {
                $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id, $this->core->getUser()->getId());
                $users_to_grade = $this->core->getQueries()->getUsersByRotatingSections($sections);
            }
            $user_ids_to_grade = array_map(function(User $user) { return $user->getId(); }, $users_to_grade);
            if (!in_array($user_id, $user_ids_to_grade)) {
                $this->core->addErrorMessage("You do not have permission to grade {$user_id}");
                return;
            }
        }
        foreach ($gradeable->getComponents() as $component) {
            if(is_array($component)) {
                if($component[0]->getId() != $_POST['gradeable_component_id']) {
                    continue;
                }
                $found = false;
                foreach($component as $peer) {
                    if($peer->getGrader() === null) {
                        $component = $peer;
                        $found = true;
                        break;
                    }
                    if($peer->getGrader()->getId() == $grader_id) {
                        $component = $peer;
                        $found = true;
                        break;
                    }
                }
                if(!$found){
                    $component = $this->core->getQueries()->getGradeableComponents($gradeable->getId())[$component[0]->getId()];
                    $marks = $this->core->getQueries()->getGradeableComponentsMarks($component->getId());
                    $component->setMarks($marks);
                }
            }
            else if ($component->getId() != $_POST['gradeable_component_id']) {
                continue;
            }
            //checks if a component has changed, i.e. a mark has been selected or unselected since last time
            //also checks if all the marks are false
            $index = 0;
            $temp_mark_selected = false;
            $all_false = true;
            $debug = "";
            $mark_modified = false;
            foreach ($component->getMarks() as $mark) {
                $temp_mark_selected = ($_POST['marks'][$index]['selected'] == 'true') ? true : false;
                if($all_false === true && $temp_mark_selected === true) {
                    $all_false = false;
                }
                if($temp_mark_selected !== $mark->getHasMark()) {
                    $mark_modified = true;
                }
                $index++;
            }
            for ($i = $index; $i < $_POST['num_mark']; $i++) {
                if ($_POST['marks'][$i]['selected'] == 'true') {
                    $all_false = false;
                    $mark_modified = true;
                    break;
                }
            }

            if($all_false === true) {
                if($_POST['custom_message'] != "" || floatval($_POST['custom_points']) != 0) {
                    $all_false = false;
                }
            }

            if($mark_modified === false) {
                if ($component->getComment() != $_POST['custom_message']) {
                    $mark_modified = true;
                }
                if ($component->getScore() != $_POST['custom_points']) {
                    $mark_modified = true;
                }
            }
            //if no gradeable id exists adds one to the gradeable data
            if($gradeable->getGdId() == null) {
                $gradeable->saveGradeableData();
            }
            if($all_false === true) {
                $component->deleteData($gradeable->getGdId());
                $debug = 'delete';
            } else {
                if($mark_modified === true) { //only change the component information is the mark was modified
                    if ($component->getGrader() === null || $overwrite === "true") {
                        $component->setGrader($this->core->getUser());
                    }

                    $component->setGradedVersion($_POST['active_version']);
                    $component->setGradeTime(new \DateTime('now', $this->core->getConfig()->getTimezone()));
                    $component->setComment($_POST['custom_message']);
                    $component->setScore($_POST['custom_points']);
                    $debug = $component->saveGradeableComponentData($gradeable->getGdId());
                }
            }

            $index = 0;
            // save existing marks
            foreach ($component->getMarks() as $mark) {
                $mark->setPoints($_POST['marks'][$index]['points']);
                $mark->setNote($_POST['marks'][$index]['note']);
                $mark->setOrder($_POST['marks'][$index]['order']);
                $mark->save();
                $_POST['marks'][$index]['selected'] == 'true' ? $mark->setHasMark(true) : $mark->setHasMark(false);
                if($all_false === false) {
                    $mark->saveGradeableComponentMarkData($gradeable->getGdId(), $component->getId(), $component->getGrader()->getId());
                }
                $index++;
            }
            // create new marks
            for ($i = $index; $i < $_POST['num_mark']; $i++) {
                $mark = new GradeableComponentMark($this->core);
                $mark->setGcId($component->getId());
                $mark->setPoints($_POST['marks'][$i]['points']);
                $mark->setNote($_POST['marks'][$i]['note']);
                $mark->setOrder($_POST['marks'][$i]['order']);
                $mark_id = $mark->save();
                $mark->setId($mark_id);
                $_POST['marks'][$index]['selected'] == 'true' ? $mark->setHasMark(true) : $mark->setHasMark(false);
                if($all_false === false) {
                    $mark->saveGradeableComponentMarkData($gradeable->getGdId(), $component->getId(), $this->core->getUser()->getId());
                }
            }
        }
        //generates the HW Report each time a mark is saved
        $hwReport = new HWReport($this->core);
        $hwReport->generateSingleReport($user_id, $gradeable_id);

        if($this->core->getUser()->getGroup() == 4) {
            $hwReport->generateSingleReport($this->core->getUser()->getId(), $gradeable_id);
        }

        $response = array('status' => 'success', 'modified' => $mark_modified, 'all_false' => $all_false, 'database' => $debug, 'overwrite' => $overwrite);
        $this->core->getOutput()->renderJson($response);
        return $response;
    }

    public function saveGradeableComment() {
        $gradeable_id = $_POST['gradeable_id'];
        $user_id = $this->core->getQueries()->getUserFromAnon($_POST['anon_id'])[$_POST['anon_id']];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $user_id);
        $gradeable->setOverallComment($_POST['gradeable_comment']);
        $gradeable->saveGradeableData();
        $hwReport = new HWReport($this->core);
        $hwReport->generateSingleReport($user_id, $gradeable_id);
    }

    public function getMarkDetails() {
        //gets all the details from the database of a mark to readd it to the view
        $gradeable_id = $_POST['gradeable_id'];
        $user_id = $this->core->getQueries()->getUserFromAnon($_POST['anon_id'])[$_POST['anon_id']];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $user_id);
        foreach ($gradeable->getComponents() as $question) {
            if(is_array($question)) {
                if($question[0]->getId() != $_POST['gradeable_component_id']) {
                    continue;
                }
                foreach($question as $cmpt) {
                    if($cmpt->getGrader() == null) {
                        $component = $cmpt;
                        break;
                    }
                    if($cmpt->getGrader()->getId() == $this->core->getUser()->getId()) {
                        $component = $cmpt;
                        break;
                    }
                }
            }
            else {
                $component = $question;
                if($component->getId() != $_POST['gradeable_component_id']) {
                    continue;
                }
            }
            $return_data = array();
            foreach ($component->getMarks() as $mark) {
                $temp_array = array();
                $temp_array['score'] = $mark->getPoints();
                $temp_array['note'] = $mark->getNote();
                $temp_array['has_mark'] = $mark->getHasMark();
                $return_data[] = $temp_array;
            }
            $temp_array = array();
            $temp_array['custom_score'] = $component->getScore();
            $temp_array['custom_note'] = $component->getComment();
            $return_data[] = $temp_array;
        }

        $response = array('status' => 'success', 'data' => $return_data);
        $this->core->getOutput()->renderJson($response);
        return $response;
    }

    public function getGradeableComment() {
        $gradeable_id = $_POST['gradeable_id'];
        $user_id = $this->core->getQueries()->getUserFromAnon($_POST['anon_id'])[$_POST['anon_id']];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $user_id);
        $response = array('status' => 'success', 'data' => $gradeable->getOverallComment());
        $this->core->getOutput()->renderJson($response);
        return $response;
    }

    public function getUsersThatGotTheMark() {
        $gradeable_id = $_POST['gradeable_id'];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id);
        $gcm_order = $_POST['order_num'];
        $return_data;
        $name_info;
        foreach ($gradeable->getComponents() as $component) {
            if ($component->getId() != $_POST['gradeable_component_id']) {
                continue;
            } else {
                foreach ($component->getMarks() as $mark) {
                    if ($mark->getOrder() == intval($gcm_order)) {
                        $return_data = $this->core->getQueries()->getDataFromGCMD($component->getId(), $mark);
                        $name_info['question_name'] = $component->getTitle();
                        $name_info['mark_note'] = $mark->getNote();
                    }
                }
            }
        }

        $sections = array();
        $this->getStats($gradeable, $sections);

        $response = array('status' => 'success', 'data' => $return_data, 'sections' => $sections, 'name_info' => $name_info);
        $this->core->getOutput()->renderJson($response);
        return $response;
    }

    private function getStats($gradeable, &$sections, $graders=array(), $total_users=array(), $no_team_users=array(), $graded_components=array()) {
        $gradeable_id = $gradeable->getId();
        if ($gradeable->isGradeByRegistration()) {
            if(!$this->core->getUser()->accessFullGrading()){
                $sections = $this->core->getUser()->getGradingRegistrationSections();
            }
            else {
                $sections = $this->core->getQueries()->getRegistrationSections();
                foreach ($sections as $i => $section) {
                    $sections[$i] = $section['sections_registration_id'];
                }
            }
            $section_key='registration_section';
            if (count($sections) > 0) {
                $graders = $this->core->getQueries()->getGradersForRegistrationSections($sections);
            }
        }
        else {
            if(!$this->core->getUser()->accessFullGrading()){
                $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id, $this->core->getUser()->getId());
            }
            else {
                $sections = $this->core->getQueries()->getRotatingSections();
                foreach ($sections as $i => $section) {
                    $sections[$i] = $section['sections_rotating_id'];
                }
            }
            $section_key='rotating_section';
            if (count($sections) > 0) {
                $graders = $this->core->getQueries()->getGradersForRotatingSections($gradeable_id, $sections);
            }
        }

        if (count($sections) > 0) {
            if ($gradeable->isTeamAssignment()) {
                $total_users = $this->core->getQueries()->getTotalTeamCountByGradingSections($gradeable_id, $sections, $section_key);
                $no_team_users = $this->core->getQueries()->getUsersWithoutTeamByGradingSections($gradeable_id, $sections, $section_key);
                $graded_components = $this->core->getQueries()->getGradedComponentsCountByTeamGradingSections($gradeable_id, $sections, $section_key);
            }
            else {
                $total_users = $this->core->getQueries()->getTotalUserCountByGradingSections($sections, $section_key);
                $no_team_users = array();
                $graded_components = $this->core->getQueries()->getGradedComponentsCountByGradingSections($gradeable_id, $sections, $section_key);
            }
        }

        $num_components = $this->core->getQueries()->getTotalComponentCount($gradeable_id);
        $sections = array();
        if (count($total_users) > 0) {
            foreach ($total_users as $key => $value) {
                $sections[$key] = array(
                    'total_components' => $value * $num_components,
                    'graded_components' => 0,
                    'graders' => array()
                );
                if ($gradeable->isTeamAssignment()) {
                    $sections[$key]['no_team'] = $no_team_users[$key];
                }
                if (isset($graded_components[$key])) {
                    $sections[$key]['graded_components'] = intval($graded_components[$key]);
                }
                if (isset($graders[$key])) {
                    $sections[$key]['graders'] = $graders[$key];
                }
            }
        }
    }
}
