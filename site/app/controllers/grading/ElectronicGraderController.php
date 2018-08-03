<?php

namespace app\controllers\grading;

use app\controllers\AbstractController;
use app\libraries\DiffViewer;
use app\models\gradeable\Component;
use app\models\gradeable\GradedComponent;
use app\models\gradeable\Mark;
use app\models\gradeable\TaGradedGradeable;
use app\models\GradeableAutocheck;
use app\models\Team;
use app\models\User;
use \app\libraries\GradeableType;
use app\models\Gradeable;
use app\models\GradeableComponent;
use app\models\GradeableComponentMark;
use app\libraries\FileUtils;
use app\views\AutoGradingView;
use app\controllers\GradingController;


class ElectronicGraderController extends GradingController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'details':
                $this->showDetails();
                break;
            case 'submit_team_form':
                $this->adminTeamSubmit();
                break;
            case 'export_teams':
                $this->exportTeams();
                break;
            case 'import_teams':
                $this->importTeams();
                break;    
            case 'grade':
                $this->showGrading();
                break;
            case 'save_graded_component':
                $this->ajaxSaveGradedComponent();
                break;
            case 'save_mark':
                $this->ajaxSaveMark();
                break;
            case 'save_mark_order':
                $this->ajaxSaveMarkOrder();
                break;
            case 'save_general_comment':
                $this->saveGeneralComment();
                break;
            case 'get_mark_data':
                $this->ajaxGetGradedComponent();
                break;
            case 'get_gradeable_comment':
                $this->getGradeableComment();
                break;
            case 'get_marked_users':
                $this->getUsersThatGotTheMark();
                break;
            case 'add_one_new_mark':
                $this->addOneMark();
                break;
            case 'delete_one_mark':
                $this->deleteOneMark();
                break;
            case 'load_student_file':
                $this->ajaxGetStudentOutput();
                break;
            case 'verify_grader':
                $this->verifyGrader();
                break;
            case 'verify_all':
                $this->verifyGrader(true);
                break;
            case 'remove_empty':
                $this->ajaxRemoveEmpty();
                break;
            case 'pdf_annotation_fullpage':
                $this->showPDFAnnotationFullPage();
                break;
            case 'pdf_annotation_embedded':
                $this->showEmbeddedPDFAnnotation();
                break;
            case 'save_pdf_annotation':
                $this->savePDFAnnotation();
                break;
            default:
                $this->showStatus();
                break;
        }
    }

    /**
     * Checks that a given diff viewer option is valid using DiffViewer::isValidSpecialCharsOption
     * @param string $option
     * @return bool
     */
    private function validateDiffViewerOption(string $option) {
        if (!DiffViewer::isValidSpecialCharsOption($option)) {
            $this->core->getOutput()->renderJsonFail('Invalid diff viewer option parameter');
            return false;
        }
        return true;
    }
    
    public function savePDFAnnotation(){
        //Save the annotation layer to a folder.
        $annotation_layer = $_POST['annotation_layer'];
        $annotation_info = $_POST['GENERAL_INFORMATION'];
        $grader_id = $this->core->getUser()->getId();
        $course_path = $this->core->getConfig()->getCoursePath();
        $active_version = $this->core->getQueries()->getGradeable($annotation_info['gradeable_id'], $annotation_info['user_id'])->getActiveVersion();
        $annotation_gradeable_path = FileUtils::joinPaths($course_path, 'annotations', $annotation_info['gradeable_id']);
        if(!FileUtils::createDir($annotation_gradeable_path) && !is_dir($annotation_gradeable_path)){
            $this->core->addErrorMessage("Creating annotation gradeable folder failed");
            return false;
        }
        $annotation_user_path = FileUtils::joinPaths($annotation_gradeable_path, $annotation_info['user_id']);
        if(!FileUtils::createDir($annotation_user_path) && !is_dir($annotation_user_path)){
            $this->core->addErrorMessage("Creating annotation user folder failed");
            return false;
        }
        $annotation_version_path = FileUtils::joinPaths($annotation_user_path, $active_version);
        if(!FileUtils::createDir($annotation_version_path) && !is_dir($annotation_version_path)){
            $this->core->addErrorMessage("Creating annotation version folder failed");
            return false;
        }
        $new_file_name = preg_replace('/\\.[^.\\s]{3,4}$/', '', $annotation_info['file_name']) . "_" .$grader_id .'.json';
        file_put_contents(FileUtils::joinPaths($annotation_version_path, $new_file_name), $annotation_layer);
        return true;
    }

    public function showEmbeddedPDFAnnotation(){
        //This is the embedded pdf annotator that we built.
        $gradeable_id = $_POST['gradeable_id'] ?? NULL;
        $user_id = $_POST['user_id'] ?? NULL;
        $filename = $_POST['filename'] ?? NULL;
        $active_version = $this->core->getQueries()->getGradeable($gradeable_id, $user_id)->getActiveVersion();
        $annotation_file_name = preg_replace('/\\.[^.\\s]{3,4}$/', '', $filename). '_' .$this->core->getUser()->getId().'.json';
        $annotation_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'annotations', $gradeable_id, $user_id, $active_version, $annotation_file_name);
        $annotation_jsons = [];
        if(is_file($annotation_path)) {
            $dir_iter = new \DirectoryIterator(dirname($annotation_path . '/'));
            foreach ($dir_iter as $fileinfo) {
                if (!$fileinfo->isDot()) {
                    $grader_id = preg_replace('/\\.[^.\\s]{3,4}$/', '', $fileinfo->getFilename());
                    $grader_id = explode('_', $grader_id)[1];
                    $annotation_jsons[$grader_id] = file_get_contents($fileinfo->getPathname());
                }
            }
        }
        $this->core->getOutput()->useFooter(false);
        $this->core->getOutput()->useHeader(false);
        //TODO: Add a new view
        return $this->core->getOutput()->renderTwigOutput('grading/electronic/PDFAnnotationEmbedded.twig', [
            'gradeable_id' => $gradeable_id,
            'grader_id' => $this->core->getUser()->getId(),
            'user_id' => $user_id,
            'filename' => $filename,
            'annotation_jsons' => json_encode($annotation_jsons, 128)
        ]);
    }

    public function showPDFAnnotationFullPage(){
        //This shows the pdf-annotate.js library's default pdf annotator. It might be useful in the future to have
        //a full-sized annotator, so keeping this in for now.
        $this->core->getOutput()->useFooter(false);
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->renderOutput(array('grading', 'PDFAnnotation'), 'showAnnotationPage');
    }

    /**
     * Checks that a given diff viewer type is valid using DiffViewer::isValidType
     * @param string $type
     * @return bool
     */
    private function validateDiffViewerType(string $type) {
        if (!DiffViewer::isValidType($type)) {
            $this->core->getOutput()->renderJsonFail('Invalid diff viewer type parameter');
            return false;
        }
        return true;
    }

    /**
     * Method for getting whitespace information for the diff viewer
     */
    public function ajaxRemoveEmpty() {
        $gradeable_id = $_REQUEST['gradeable_id'] ?? '';
        $submitter_id = $_REQUEST['who_id'] ?? '';
        $index = $_REQUEST['index'] ?? '';
        $option = $_REQUEST['option'] ?? 'original';
        $version = $_REQUEST['version'] ?? '';
        $type = $_REQUEST['which'] ?? 'actual';
        $autocheck_cnt = $_REQUEST['autocheck_cnt'] ?? '0';

        //There are three options: original (Don't show empty space), escape (with escape codes), and unicode (with characters)
        if (!$this->validateDiffViewerOption($option)) {
            return;
        }

        // Type can be either 'actual' or 'expected'
        if (!$this->validateDiffViewerType($type)) {
            return;
        }

        // Get the gradeable
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }

        // Get the graded gradeable
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $submitter_id);
        if ($graded_gradeable === false) {
            return;
        }

        // get the requested version
        $version_instance = $this->tryGetVersion($graded_gradeable->getAutoGradedGradeable(), $version);
        if ($version_instance === false) {
            return;
        }

        // Get the requested testcase
        $testcase = $this->tryGetTestcase($version_instance, $index);
        if ($testcase === false) {
            return;
        }

        // Get the requested autocheck
        $autocheck = $this->tryGetAutocheck($testcase, $autocheck_cnt);
        if ($autocheck === false) {
            return;
        }

        try {
            $results = $this->removeEmpty($autocheck, $option, $type);
            $this->core->getOutput()->renderJsonSuccess($results);
        } catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }

    private function removeEmpty(GradeableAutocheck $autocheck, string $option, string $type) {
        $diff_viewer = $autocheck->getDiffViewer();

        //There are currently two views, the view of student's code and the expected view.
        if ($type === DiffViewer::ACTUAL) {
            $html = $diff_viewer->getDisplayActual($option);
        } else {
            $html = $diff_viewer->getDisplayExpected($option);
        }
        $white_spaces = $diff_viewer->getWhiteSpaces();
        return ['html' => $html, 'whitespaces' => $white_spaces];
    }

    private function verifyGrader($verifyAll = false){
        //check that I am able to verify.
        if ($verifyAll) {
            if (!$this->core->getAccess()->canI("grading.electronic.verify_all")) {
                $this->core->addErrorMessage("You do not have the proper privileges to verify this grade.");
                return;
            }
        } else {
            if (!$this->core->getAccess()->canI("grading.electronic.verify_grader")) {
                $this->core->addErrorMessage("You do not have the proper privileges to verify this grade.");
                return;
            }
        }

        $gradeable_id = $_POST['gradeable_id'];
        $component_id = $_POST['component_id'];
        $user_id = $this->core->getQueries()->getUserFromAnon($_POST['anon_id'])[$_POST['anon_id']];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $user_id);
        //stores whether or not we verified at least one gradeable. Should never be false at the end of an execution.
        $verified = false;
        //Search across all components for components to verify
        foreach ($gradeable->getComponents() as $component) {
            //If this component hasn't been graded, we can't verify it.
            if(!$component->getGrader()) {
                continue;
            }
            //If we are either verifying all components or this is the component we were asked to verify,
            //verify the component.
            if($verifyAll || $component->getId() == $component_id){
                //Only verify the component if we weren't already the grader.
                if($component->getGrader()->getId() !== $this->core->getUser()->getId()){
                    $component->setGrader($this->core->getUser());
                    $component->saveGradeableComponentData($gradeable->getGdId());
                    $verified = true;
                }
                //If we aren't verifying all, we have verified the only component we need to.
                if(!$verifyAll && $component->getId() == $component_id) {
                    break;
                }
            }
        }

        if($verified){
            return;
        }else{
            $this->core->addErrorMessage("Gradeable component does not exist or was previously verified by you.");
        }
    }
    /**
     * Shows statistics for the grading status of a given electronic submission. This is shown to all full access
     * graders. Limited access graders will only see statistics for the sections they are assigned to.
     */
    public function showStatus() {
        $gradeable_id = $_REQUEST['gradeable_id'];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id);

        if (!$this->core->getAccess()->canI("grading.electronic.status", ["gradeable" => $gradeable])) {
            $this->core->addErrorMessage("You do not have permission to grade {$gradeable->getName()}");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }

        $gradeableUrl = $this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'gradeable_id' => $gradeable_id));
        $this->core->getOutput()->addBreadcrumb("{$gradeable->getName()} Grading", $gradeableUrl);
        $peer = false;
        if ($gradeable->getPeerGrading() && ($this->core->getUser()->getGroup() == User::GROUP_STUDENT)) {
            $peer = true;
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
        $component_averages = array();
        $autograded_average = null;
        $overall_average = null;
        $num_submitted = array();
        $num_unsubmitted = 0 ;
        $total_indvidual_students = 0;
        $viewed_grade = 0;
        $regrade_requests = $this->core->getQueries()->getNumberRegradeRequests($gradeable_id);
        if ($peer) {
            $peer_grade_set = $gradeable->getPeerGradeSet();
            $total_users = $this->core->getQueries()->getTotalUserCountByGradingSections($sections, 'registration_section');
            $num_components = $gradeable->getNumPeerComponents();
            $graded_components = $this->core->getQueries()->getGradedPeerComponentsByRegistrationSection($gradeable_id, $sections);
            $my_grading = $this->core->getQueries()->getNumGradedPeerComponents($gradeable->getId(), $this->core->getUser()->getId());
            $component_averages = array();
            $autograded_average = null;
            $overall_average = null;
            $section_key='registration_section';
        }
        else if ($gradeable->isGradeByRegistration()) {
            if(!$this->core->getAccess()->canI("grading.electronic.status.full")) {
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
        //grading by rotating section
        else {
            if(!$this->core->getAccess()->canI("grading.electronic.status.full")) {
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
        //Check if this is a team project or a single-user project
        if($gradeable->isTeamAssignment()){
            $num_submitted = $this->core->getQueries()->getSubmittedTeamCountByGradingSections($gradeable_id, $sections, 'registration_section');
        }
        else{
            $num_submitted = $this->core->getQueries()->getTotalSubmittedUserCountByGradingSections($gradeable_id, $sections, $section_key);
        }
        if (count($sections) > 0) {
            if ($gradeable->isTeamAssignment()) {
                $total_users = $this->core->getQueries()->getTotalTeamCountByGradingSections($gradeable_id, $sections, $section_key);
                $no_team_users = $this->core->getQueries()->getUsersWithoutTeamByGradingSections($gradeable_id, $sections, $section_key);
                $team_users = $this->core->getQueries()->getUsersWithTeamByGradingSections($gradeable_id, $sections, $section_key);
            }
            else {
                $total_users = $this->core->getQueries()->getTotalUserCountByGradingSections($sections, $section_key);
                $no_team_users = array();
                $team_users = array();
            }
            $graded_components = $this->core->getQueries()->getGradedComponentsCountByGradingSections($gradeable_id, $sections, $section_key, $gradeable->isTeamAssignment());
            $component_averages = $this->core->getQueries()->getAverageComponentScores($gradeable_id, $section_key, $gradeable->isTeamAssignment());
            $autograded_average = $this->core->getQueries()->getAverageAutogradedScores($gradeable_id, $section_key, $gradeable->isTeamAssignment());
            $overall_average = $this->core->getQueries()->getAverageForGradeable($gradeable_id, $section_key, $gradeable->isTeamAssignment());
            $num_components = $gradeable->getNumTAComponents();
            $viewed_grade = $this->core->getQueries()->getNumUsersWhoViewedGrade($gradeable_id);
        }
        $sections = array();
        //Either # of teams or # of students (for non-team assignments). Either case
        // this is the max # of submitted copies for this gradeable.
        $total_submissions = 0;
        if (count($total_users) > 0) {
            foreach ($total_users as $key => $value) {
                if ($key == 'NULL') continue;
                $total_submissions += $value;
            }
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
                    if(array_key_exists($key, $num_submitted)){
                        $sections[$key] = array(
                            'total_components' => $num_submitted[$key] * $num_components,
                            'graded_components' => 0,
                            'graders' => array()
                        );
                    } else{
                        $sections[$key] = array(
                            'total_components' => 0,
                            'graded_components' => 0,
                            'graders' => array()
                        );
                    }
                    if ($gradeable->isTeamAssignment()) {
                        $sections[$key]['no_team'] = $no_team_users[$key];
                        $sections[$key]['team'] = $team_users[$key];
                    }
                    if (isset($graded_components[$key])) {
                        // Clamp to total components if unsubmitted assigment is graded for whatever reason
                        $sections[$key]['graded_components'] = min(intval($graded_components[$key]), $sections[$key]['total_components']);
                    }
                    if (isset($graders[$key])) {
                        $sections[$key]['graders'] = $graders[$key];

                        if ($key !== "NULL") {
                            $valid_graders = array();
                            foreach ($graders[$key] as $valid_grader) {
                                /* @var User $valid_grader */
                                if ($this->core->getAccess()->canUser($valid_grader, "grading.electronic.grade", ["gradeable" => $gradeable])) {
                                    $valid_graders[] = $valid_grader->getDisplayedFirstName();
                                }
                            }
                            $sections[$key]["valid_graders"] = $valid_graders;
                        }
                    }
                }
            }
        }
        $registered_but_not_rotating = count($this->core->getQueries()->getRegisteredUsersWithNoRotatingSection());
        $rotating_but_not_registered = count($this->core->getQueries()->getUnregisteredStudentsWithRotatingSection());

        $show_warnings = $this->core->getAccess()->canI("grading.electronic.status.warnings");

        $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'statusPage', $gradeable, $sections, $component_averages, $autograded_average, $overall_average, $total_submissions, $registered_but_not_rotating, $rotating_but_not_registered, $viewed_grade, $section_key, $regrade_requests, $show_warnings);
    }
    public function showDetails() {
        $gradeable_id = $_REQUEST['gradeable_id'];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id);

        $gradeableUrl = $this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'gradeable_id' => $gradeable_id));
        $this->core->getOutput()->addBreadcrumb("{$gradeable->getName()} Grading", $gradeableUrl);

        $this->core->getOutput()->addBreadcrumb('Student Index');

        if ($gradeable === null) {
            $this->core->getOutput()->renderOutput('Error', 'noGradeable', $gradeable_id);
            return;
        }
        $peer = ($gradeable->getPeerGrading() && $this->core->getUser()->getGroup() == User::GROUP_STUDENT);
        if (!$this->core->getAccess()->canI("grading.electronic.details", ["gradeable" => $gradeable])) {
            $this->core->addErrorMessage("You do not have permission to grade {$gradeable->getName()}");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }

        //Checks to see if the Grader has access to all users in the course,
        //Will only show the sections that they are graders for if not TA or Instructor
        $can_show_all = $this->core->getAccess()->canI("grading.electronic.details.show_all");
        $show_all = isset($_GET['view']) && $_GET['view'] === "all" && $can_show_all;

        $students = array();
        //If we are peer grading, load in all students to be graded by this peer.
        if ($peer) {
            $student_ids = $this->core->getQueries()->getPeerAssignment($gradeable->getId(), $this->core->getUser()->getId());
            $graders = array();
            $section_key = "registration_section";
        }
        else if ($gradeable->isGradeByRegistration()) {
            $section_key = "registration_section";
            $sections = $this->core->getUser()->getGradingRegistrationSections();
            if (!$show_all) {
                $students = $this->core->getQueries()->getUsersByRegistrationSections($sections);
            }
            $graders = $this->core->getQueries()->getGradersForRegistrationSections($sections);
        }
        else {
            $section_key = "rotating_section";
            $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id,
                $this->core->getUser()->getId());
            if (!$show_all) {
                $students = $this->core->getQueries()->getUsersByRotatingSections($sections);
            }
            $graders = $this->core->getQueries()->getGradersForRotatingSections($gradeable->getId(), $sections);
        }
        if ($show_all) {
            $students = $this->core->getQueries()->getAllUsers($section_key);
        }
        if(!$peer) {
            $student_ids = array_map(function(User $student) { return $student->getId(); }, $students);
        }

        $show_empty_teams = $this->core->getAccess()->canI("grading.electronic.details.show_empty_teams");
        $empty_teams = array();
        if ($gradeable->isTeamAssignment()) {
            // Only give getGradeables one User ID per team
            $all_teams = $this->core->getQueries()->getTeamsByGradeableId($gradeable_id);
            foreach($all_teams as $team) {
                $student_ids = array_diff($student_ids, $team->getMembers());
                $team_section = $gradeable->isGradeByRegistration() ? $team->getRegistrationSection() : $team->getRotatingSection();
                if ($team->getSize() > 0 && (in_array($team_section, $sections) || $show_all)) {
                    $student_ids[] = $team->getLeaderId();
                }
                if ($team->getSize() === 0 && $show_empty_teams) {
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

        if ($peer) {
            $grading_count = $gradeable->getPeerGradeSet();
        } else if ($gradeable->isGradeByRegistration()) {
            $grading_count = count($this->core->getUser()->getGradingRegistrationSections());
        } else {
            $grading_count = count($this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable->getId(), $this->core->getUser()->getId()));
        }

        $show_all_sections_button = $can_show_all;
        $show_edit_teams = $this->core->getAccess()->canI("grading.electronic.show_edit_teams") && $gradeable->isTeamAssignment();
        $show_import_teams_button = $show_edit_teams && (count($all_teams) > count($empty_teams));
        $show_export_teams_button = $show_edit_teams && (count($all_teams) == count($empty_teams));

        $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'detailsPage', $gradeable, $rows, $graders, $empty_teams, $show_all_sections_button, $show_import_teams_button, $show_export_teams_button, $show_edit_teams);

        if ($show_edit_teams) {
            $all_reg_sections = $this->core->getQueries()->getRegistrationSections();
            $key = 'sections_registration_id';
            foreach ($all_reg_sections as $i => $section) {
                $all_reg_sections[$i] = $section[$key];
            }

            $all_rot_sections = $this->core->getQueries()->getRotatingSections();
            $key = 'sections_rotating_id';
            
            foreach ($all_rot_sections as $i => $section) {
                $all_rot_sections[$i] = $section[$key];
            }
            $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'adminTeamForm', $gradeable, $all_reg_sections, $all_rot_sections);
            $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'importTeamForm', $gradeable);
        }
    }

    public function importTeams() {
        $gradeable_id = (isset($_REQUEST['gradeable_id'])) ? $_REQUEST['gradeable_id'] : null;
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id);

        $return_url = $this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'details','gradeable_id'=>$gradeable_id));

        if ($gradeable == null) {
            $this->core->addErrorMessage("Failed to load gradeable: {$gradeable_id}");
            $this->core->redirect($return_url);
        }

        if (!$this->core->getAccess()->canI("grading.electronic.import_teams", ["gradeable" => $gradeable])) {
            $this->core->addErrorMessage("You do not have permission to do that.");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }

        if (!$gradeable->isTeamAssignment()) {
            $this->core->addErrorMessage("{$gradeable->getName()} is not a team assignment");
            $this->core->redirect($return_url);
        }

        if ($_FILES['upload_team']['name'] == "") {
            $this->core->addErrorMessage("No input file specified");
            $this->core->redirect($return_url);
        }

        $csv_file = $_FILES['upload_team']['tmp_name'];
        register_shutdown_function(
            function() use ($csv_file) {
                unlink($csv_file);
            }
        );
        ini_set("auto_detect_line_endings", true);

        $contents = file($csv_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($contents === false) {
            $this->core->addErrorMessage("File was not properly uploaded. Contact your sysadmin.");
            $this->core->redirect($return_url);
        }

        $row_num=1;
        $error_message="";
        $new_teams_members = array();
        foreach($contents as $content) {
            $vals = str_getcsv($content);
            $vals = array_map('trim', $vals);
            if(count($vals) != 6) {
                $error_message .= "ERROR on row {$row_num}, csv row do not follow specified format<br>";
                continue;
            }
            if($row_num == 1) {
                $row_num += 1;
                continue;
            }
            $team_id = $vals[3];
            $user_id = $vals[2];
            
            if ($this->core->getQueries()->getUserById($user_id) === null) {
                $error_message .= "ERROR on row {$row_num}, user_id doesn't exists<br>";
                continue;    
            }
            if(!array_key_exists($team_id, $new_teams_members)) {
                $new_teams_members[$team_id] = array();
            }
            array_push($new_teams_members[$team_id], $user_id);
        }

        if($error_message != "") {
            $this->core->addErrorMessage($error_message);
            $this->core->redirect($return_url);
        }

        $gradeable_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable_id);
        if (!FileUtils::createDir($gradeable_path)) {
            $this->core->addErrorMessage("Failed to make folder for this assignment");
            $this->core->redirect($return_url);
        }    

        foreach($new_teams_members as $team_id => $members) {
            $leader_id = $members[0];
            ElectronicGraderController::CreateTeamWithLeaderAndUsers($this->core, $gradeable, $leader_id, $members);
        }

        $this->core->addSuccessMessage("All Teams are imported to the gradeable");
        $this->core->redirect($return_url);
    }

    public function exportTeams() {
        $gradeable_id = $_REQUEST['gradeable_id'];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id);

        if (!$this->core->getAccess()->canI("grading.electronic.export_teams", ["gradeable" => $gradeable])) {
            $this->core->addErrorMessage("You do not have permission to do that.");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }

        $all_teams = $this->core->getQueries()->getTeamsByGradeableId($gradeable_id);
        $nl = "\n";
        $csvdata="First Name,Last Name,User ID,Team ID,Team Registration Section,Team Rotating Section".$nl;
        foreach ($all_teams as $team) {
            if( $team->getSize() != 0) {
                foreach(($team->getMembers()) as $member_id) {
                    $user = $this->core->getQueries()->getUserById($member_id);
                    $csvdata .= $user->getDisplayedFirstName().",".$user->getLastName().",".$member_id.",".$team->getId().",".$team->getRegistrationSection().",".$team->getRotatingSection().$nl;
                }
            }    
        }
        $filename = "";
        $filename = $this->core->getConfig()->getCourse()."_".$gradeable_id."_teams.csv";
        $this->core->getOutput()->renderFile($csvdata, $filename);
        return $csvdata;
    }    

    public function adminTeamSubmit() {
        if (!$this->core->getAccess()->canI("grading.electronic.submit_team_form")) {
            $this->core->addErrorMessage("You do not have permission to do that.");
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
                continue;
            }
            if(in_array($id, $user_ids)) {
                $this->core->addErrorMessage("ERROR: {$id} is already on this team");
                $this->core->redirect($return_url);
            }
        }
        $new_team = $_POST['new_team'] === 'true' ? true : false;

        if ($new_team) {
            $leader = $_POST['new_team_user_id'];
            ElectronicGraderController::CreateTeamWithLeaderAndUsers($this->core, $gradeable, $leader, $user_ids);
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

            $reg_section = $_POST['reg_section'] === "NULL" ? null : $_POST['reg_section'];
            $rot_section = $_POST['rot_section'] === "NULL" ? null : intval($_POST['rot_section']);
            $this->core->getQueries()->updateTeamRegistrationSection($team_id, $reg_section);
            $this->core->getQueries()->updateTeamRotatingSection($team_id, $rot_section);
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
                $this->core->addErrorMessage("Failed to open settings file");
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
            if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
                $this->core->addErrorMessage("Failed to write to team history to settings file");
            }
        }   
        
        $this->core->redirect($return_url);
    }

    static public function createTeamWithLeaderAndUsers($core, $gradeable, $leader, $user_ids){
        $team_leader_id = null;
        $gradeable_id = $gradeable->getId();
        foreach($user_ids as $id) {
            if($id === "undefined" || $id === "")
            {
                continue;
            }
            if ($core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $id) !== null) {
                $core->addErrorMessage("ERROR: {$id} is already on a team");
                return;
            }
            if ($id === $leader) {
                $team_leader_id = $id;
            }
        }
        if ($team_leader_id === null) {
            $core->addErrorMessage("ERROR: {$team_leader_id} must be on the team");
            return;
        }

        $registration_section = $core->getQueries()->getUserById($team_leader_id)->getRegistrationSection();
        $rotating_section = $core->getQueries()->getUserById($team_leader_id)->getRotatingSection();

        //overwrite sections if they are available in the post
        if(isset($_POST['section']) && $_POST['section'] !== "NULL"){
            if ($gradeable->isGradeByRegistration()) {
                $registration_section = $_POST['section'] === "NULL" ? null : $_POST['section'];
            }
            else {
                $rotating_section = $_POST['section'] === "NULL" ? null : intval($_POST['section']);
            }
        }

        $team_id = $core->getQueries()->createTeam($gradeable_id, $team_leader_id, $registration_section, $rotating_section);
        foreach($user_ids as $id) {
            if($id === "undefined" or $id === ""){
                continue;
            }
            $core->getQueries()->declineAllTeamInvitations($gradeable_id, $id);
            if ($id !== $team_leader_id) $core->getQueries()->acceptTeamInvitation($team_id, $id);
        }
        $core->addSuccessMessage("Created New Team {$team_id}");

        $gradeable_path = FileUtils::joinPaths($core->getConfig()->getCoursePath(), "submissions", $gradeable_id);
        if (!FileUtils::createDir($gradeable_path)) {
            $core->addErrorMessage("Failed to make folder for this assignment");
            return;
        }

        $user_path = FileUtils::joinPaths($gradeable_path, $team_id);
        if (!FileUtils::createDir($user_path)) {
            $core->addErrorMessage("Failed to make folder for this assignment for the team");
            return;
        }

        $current_time = (new \DateTime('now', $core->getConfig()->getTimezone()))->format("Y-m-d H:i:sO")." ".$core->getConfig()->getTimezone()->getName();
        $settings_file = FileUtils::joinPaths($user_path, "user_assignment_settings.json");
        $json = array("team_history" => array(array("action" => "admin_create", "time" => $current_time,
                                                    "admin_user" => $core->getUser()->getId(), "first_user" => $team_leader_id)));
        foreach($user_ids as $id) {
            if ($id !== $team_leader_id) {
                $json["team_history"][] = array("action" => "admin_add_user", "time" => $current_time,
                                                "admin_user" => $core->getUser()->getId(), "added_user" => $id);
            }
        }
        if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
            $core->addErrorMessage("Failed to write to team history to settings file");
        }
    }

    public function showGrading() {
        $gradeable_id = $_REQUEST['gradeable_id'];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id);
        $peer = false;
        if($gradeable->getPeerGrading() && $this->core->getUser()->getGroup() == User::GROUP_STUDENT) {
            $peer = true;
        }

        $gradeableUrl = $this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'gradeable_id' => $gradeable_id));
        $this->core->getOutput()->addBreadcrumb("{$gradeable->getName()} Grading", $gradeableUrl);
        $indexUrl = $this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'action' => 'details', 'gradeable_id' => $gradeable_id));
        $this->core->getOutput()->addBreadcrumb('Student Index', $indexUrl);

        $graded = 0;
        $total = 0;
        $team = $gradeable->isTeamAssignment();
        if($peer) {
            $section_key = 'registration_section';
            $user_ids_to_grade = $this->core->getQueries()->getPeerAssignment($gradeable->getId(), $this->core->getUser()->getId());
            $total = $gradeable->getPeerGradeSet();
            $graded = $this->core->getQueries()->getNumGradedPeerComponents($gradeable->getId(), $this->core->getUser()->getId()) / $gradeable->getNumPeerComponents();
        }
        else if ($gradeable->isGradeByRegistration()) {
            $section_key = "registration_section";
            $sections = $this->core->getUser()->getGradingRegistrationSections();
            if ($this->core->getAccess()->canI("grading.electronic.grade.if_no_sections_exist") && $sections == null) {
                $sections = $this->core->getQueries()->getRegistrationSections();
                for ($i = 0; $i < count($sections); $i++) {
                    $sections[$i] = $sections[$i]['sections_registration_id'];
                }
            }
            if ($team) {
                $teams_to_grade = $this->core->getQueries()->getTeamsByGradeableId($gradeable_id);
                //order teams first by registration section, then by leader id.
                usort($teams_to_grade, function(Team $a, Team $b) {
                    if($a->getRegistrationSection() == $b->getRegistrationSection())
                        return $a->getLeaderId() < $b->getLeaderId() ? -1 : 1;
                    return $a->getRegistrationSection() < $b->getRegistrationSection() ? -1 : 1;
                });

            }
            else {
                $users_to_grade = $this->core->getQueries()->getUsersByRegistrationSections($sections,$orderBy="registration_section,user_id;");
            }
            if($team){
                $graded = array_sum($this->core->getQueries()->getGradedComponentsCountByGradingSections($gradeable_id, $sections, 'registration_section',$team));
                $total = array_sum($this->core->getQueries()->getTotalTeamCountByGradingSections($gradeable_id, $sections, 'registration_section'));
                $total_submitted=array_sum($this->core->getQueries()->getSubmittedTeamCountByGradingSections($gradeable_id, $sections, 'registration_section'));
            }
            else {
                $graded = array_sum($this->core->getQueries()->getGradedComponentsCountByGradingSections($gradeable_id, $sections, 'registration_section', $team));
                $total = array_sum($this->core->getQueries()->getTotalUserCountByGradingSections($sections, 'registration_section'));
                $total_submitted=array_sum($this->core->getQueries()->getTotalSubmittedUserCountByGradingSections($gradeable_id, $sections, 'registration_section'));
            }
        }
        else {
            $section_key = "rotating_section";
            $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id, $this->core->getUser()->getId());
            if ($this->core->getAccess()->canI("grading.electronic.grade.if_no_sections_exist") && $sections == null) {
                $sections = $this->core->getQueries()->getRotatingSections();
                for ($i = 0; $i < count($sections); $i++) {
                    $sections[$i] = $sections[$i]['sections_rotating_id'];
                }
            }
            if ($team) {
                $teams_to_grade = $this->core->getQueries()->getTeamsByGradeableId($gradeable_id);
                //order teams first by rotating section, then by leader id.
                usort($teams_to_grade, function($a, $b) {
                    if($a->getRotatingSection() == $b->getRotatingSection())
                        return $a->getMembers()[0] < $b->getMembers()[0] ? -1 : 1;
                    return $a->getRotatingSection() < $b->getRotatingSection() ? -1 : 1;
                });
                //$total = array_sum($this->core->getQueries()->getTotalTeamCountByGradingSections($gradeable_id, $sections, 'rotating_section'));
                $total = array_sum($this->core->getQueries()->getTotalTeamCountByGradingSections($gradeable_id, $sections, 'rotating_section'));
                $total_submitted=array_sum($this->core->getQueries()->getSubmittedTeamCountByGradingSections($gradeable_id, $sections, 'rotating_section'));
            }
            else {
                $users_to_grade = $this->core->getQueries()->getUsersByRotatingSections($sections,$orderBy="rotating_section,user_id;");
                $total = array_sum($this->core->getQueries()->getTotalUserCountByGradingSections($sections, 'rotating_section'));
                $total_submitted=array_sum($this->core->getQueries()->getTotalSubmittedUserCountByGradingSections($gradeable->getId(), $sections, 'rotating_section'));
            }
            $graded = array_sum($this->core->getQueries()->getGradedComponentsCountByGradingSections($gradeable_id, $sections, 'rotating_section', $team));
        }
        //multiplies users and the number of components a gradeable has together
        if($team) {
            $total_submitted = $total_submitted * count($gradeable->getComponents());
        }
        else {
            $total_submitted = $total_submitted * count($gradeable->getComponents());
        }
        if($total_submitted == 0) {
            $progress = 100;
        }
        else {
            $progress = round(($graded / $total_submitted) * 100, 1);
        }
        if(!$peer && !$team) {
            $user_ids_to_grade = array_map(function(User $user) { return $user->getId(); }, $users_to_grade);
        }
        if(!$peer && $team) {
            /* @var Team[] $teams_assoc */
            $teams_assoc = [];

            foreach ($teams_to_grade as $team_id) {
                $teams_assoc[$team_id->getId()] = $team_id;
                $user_ids_to_grade[] = $team_id->getId();
            }
        }
        
        //$gradeables_to_grade = $this->core->getQueries()->getGradeables($gradeable_id, $user_ids_to_grade, $section_key);

        $who_id = isset($_REQUEST['who_id']) ? $_REQUEST['who_id'] : "";
        //$who_id = isset($who_id[$_REQUEST['who_id']]) ? $who_id[$_REQUEST['who_id']] : "";

        $prev_id = "";
        $next_id = "";
        $break_next = false;
        if($who_id === ""){
            $this->core->redirect($this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'details', 
                'gradeable_id' => $gradeable_id)));
        }
        
        $index = array_search($who_id, $user_ids_to_grade);
        $not_in_my_section = false;
        //If the student isn't in our list of students to grade.
        if($index === false){
            //If we are a full access grader, let us access the student anyway (but don't set next and previous)
            $prev_id = "";
            $next_id = "";
            $not_in_my_section = true;
        }
        else {
            //If the student is in our list of students to grade, set next and previous index appropriately
            if ($index > 0) {
                $prev_id = $user_ids_to_grade[$index - 1];
            }
            if ($index < count($user_ids_to_grade) - 1) {
                $next_id = $user_ids_to_grade[$index + 1];
            }
        }

        if ($team) {
            if ($teams_assoc[$who_id] === NULL) {
                $gradeable = NULL;
            } else {
                $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $teams_assoc[$who_id]->getLeaderId());
            }
        } else {
            $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $who_id);
        }

        if (!$this->core->getAccess()->canI("grading.electronic.grade", ["gradeable" => $gradeable])) {
            $this->core->addErrorMessage("ERROR: You do not have access to grade the requested student.");
            $this->core->redirect($this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'gradeable_id' => $gradeable_id)));
        }

        $gradeable->loadResultDetails();

        $can_verify = false;
        //check if verify all button should be shown or not
        foreach ($gradeable->getComponents() as $component) {
            if (!$component->getGrader()) {
                continue;
            }
            if ($component->getGrader()->getId() !== $this->core->getUser()->getId()) {
                $can_verify = true;
                break;
            }
        }
        $can_verify = $can_verify && $this->core->getAccess()->canI("grading.electronic.verify_grader");

        $this->core->getOutput()->addInternalCss('ta-grading.css');
        $show_hidden = $this->core->getAccess()->canI("autograding.show_hidden_cases", ["gradeable" => $gradeable]);
        $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'hwGradingPage', $gradeable, $progress, $prev_id, $next_id, $not_in_my_section, $show_hidden, $can_verify);
        $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'popupStudents');
        $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'popupNewMark');
        $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'popupSettings');
    }

    /**
     * Route for saving the marks the submitter received for a component
     */
    public function ajaxSaveGradedComponent() {
        $gradeable_id = $_POST['gradeable_id'] ?? '';
        $anon_id = $_POST['anon_id'] ?? '';
        $component_id = $_POST['component_id'] ?? '';
        $custom_message = $_POST['custom_message'] ?? null;
        $custom_points = $_POST['custom_points'] ?? null;
        $component_version = $_POST['active_version'] ?? null;

        // Optional marks parameter
        $marks = $_POST['mark_ids'] ?? [];

        // Validate required parameters
        if ($custom_message === null) {
            $this->core->getOutput()->renderJsonFail('Missing custom_message parameter');
            return;
        }
        if ($custom_points === null) {
            $this->core->getOutput()->renderJsonFail('Missing custom_points parameter');
            return;
        }
        if (!is_numeric($custom_points)) {
            $this->core->getOutput()->renderJsonFail('Invalid custom_points parameter');
            return;
        }
        if ($component_version === null) {
            $this->core->getOutput()->renderJsonFail('Missing active_version parameter');
            return;
        }
        if (!ctype_digit($component_version)) {
            $this->core->getOutput()->renderJsonFail('Invalid active_version parameter');
            return;
        }

        // Convert the mark ids to integers
        $numeric_mark_ids = [];
        foreach ($marks as $mark) {
            if(!ctype_digit($mark)) {
                $this->core->getOutput()->renderJsonFail('One of provided mark ids was invalid');
                return;
            }
            $numeric_mark_ids[] = intval($mark);
        }
        $marks = $numeric_mark_ids;

        // Parse the strings into ints/floats
        $component_version = intval($component_version);
        $custom_points = floatval($custom_points);

        // Optional Parameters
        $overwrite = ($_POST['overwrite'] ?? true) === true;

        $grader = $this->core->getUser();

        // Get the gradeable
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }

        // get the component
        $component = $this->tryGetComponent($gradeable, $component_id);
        if ($component === false) {
            return;
        }

        // Get user id from the anon id
        $user_id = $this->tryGetUserIdFromAnonId($anon_id);
        if ($user_id === false) {
            return;
        }

        // Get the graded gradeable
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $user_id);
        if ($graded_gradeable === false) {
            return;
        }

        // checks if user has permission
        if (!$this->core->getAccess()->canI("grading.electronic.save_graded_component", ["gradeable" => $graded_gradeable, "component" => $component])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to save component/marks');
            return;
        }

        // Get / create the TA grade
        $ta_graded_gradeable = $graded_gradeable->getOrCreateTaGradedGradeable();

        // Get / create the graded component
        $graded_component = $ta_graded_gradeable->getOrCreateGradedComponent($component, $grader, true);

        try {
            // Once we've parsed the inputs and checked permissions, perform the operation
            $results = $this->saveGradedComponent($ta_graded_gradeable, $graded_component, $grader, $custom_points,
                $custom_message, $marks, $component_version, $overwrite);
            $this->core->getOutput()->renderJsonSuccess($results);
        } catch (\InvalidArgumentException $e) {
            $this->core->getOutput()->renderJsonFail($e->getMessage());
        } catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }

    public function saveGradedComponent(TaGradedGradeable $ta_graded_gradeable, GradedComponent $graded_component, User $grader, float $custom_points, string $custom_message, array $mark_ids, int $component_version, bool $overwrite) {
        // Only update the grader if we're set to overwrite it
        if ($overwrite) {
            $graded_component->setGrader($grader);
        }
        $version_updated = $graded_component->getGradedVersion() !== $component_version;
        if ($version_updated) {
            $graded_component->setGradedVersion($component_version);
        }
        $graded_component->setComment($custom_message);
        $graded_component->setScore($custom_points);
        $graded_component->setGradeTime(new \DateTime('now', $this->core->getConfig()->getTimezone()));

        // Set the marks the submitter received
        $graded_component->setMarkIds($mark_ids);

        // Check if this graded component should be deleted
        if (count($graded_component->getMarkIds()) === 0
            && $graded_component->getScore() === 0.0
            && $graded_component->getComment() === '') {
            $ta_graded_gradeable->deleteGradedComponent($graded_component->getComponent(), $graded_component->getGrader());
            $graded_component = null;
        }

        // TODO: is this desirable
        // Reset the user viewed date since we updated the grade
        $ta_graded_gradeable->resetUserViewedDate();

        // Finally, save the changes to the database
        $this->core->getQueries()->saveTaGradedGradeable($ta_graded_gradeable);

        // Response 'data'
        return [
            'component_reset' => $graded_component === null,
            'version_updated' => $version_updated
        ];
    }

    /**
     * Route for saving a mark's title/point value
     */
    public function ajaxSaveMark() {
        // Required parameters
        $gradeable_id = $_POST['gradeable_id'] ?? '';
        $component_id = $_POST['component_id'] ?? '';
        $mark_id = $_POST['mark_id'] ?? '';
        $points = $_POST['points'] ?? '';
        $title = $_POST['note'] ?? null;

        // Validate required parameters
        if ($title === null) {
            $this->core->getOutput()->renderJsonFail('Missing title parameter');
            return;
        }
        if ($points === null) {
            $this->core->getOutput()->renderJsonFail('Missing points parameter');
            return;
        }
        if (!is_numeric($points)) {
            $this->core->getOutput()->renderJsonFail('Invalid points parameter');
            return;
        }

        $points = floatval($points);

        // Get the gradeable
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }

        // get the component
        $component = $this->tryGetComponent($gradeable, $component_id);
        if ($component === false) {
            return;
        }

        // get the mark
        $mark = $this->tryGetMark($component, $mark_id);
        if ($mark === false) {
            return;
        }

        // checks if user has permission
        if (!$this->core->getAccess()->canI("grading.electronic.save_mark", ["gradeable" => $gradeable])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to save marks');
            return;
        }

        try {
            // Once we've parsed the inputs and checked permissions, perform the operation
            $this->saveMark($mark, $points, $title);
            $this->core->getOutput()->renderJsonSuccess();
        } catch (\InvalidArgumentException $e) {
            $this->core->getOutput()->renderJsonFail($e->getMessage());
        } catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }

    public function saveMark(Mark $mark, float $points, string $title) {
        if($mark->getPoints() !== $points) {
            $mark->setPoints($points);
        }
        if($mark->getTitle() !== $title) {
            $mark->setTitle($title);
        }
        $this->core->getQueries()->updateGradeable($mark->getComponent()->getGradeable());
    }

    public function ajaxSaveMarkOrder() {
        // Required parameters
        $gradeable_id = $_POST['gradeable_id'] ?? '';
        $component_id = $_POST['component_id'] ?? '';
        $order = json_decode($_POST['order'] ?? '[]', true);

        // Validate required parameters
        if (count($order) === 0) {
            $this->core->getOutput()->renderJsonFail('Missing order parameter');
            return;
        }

        // Get the gradeable
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }

        // get the component
        $component = $this->tryGetComponent($gradeable, $component_id);
        if ($component === false) {
            return;
        }

        // checks if user has permission
        if (!$this->core->getAccess()->canI("grading.electronic.save_mark", ["gradeable" => $gradeable])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to save marks');
            return;
        }

        try {
            // Once we've parsed the inputs and checked permissions, perform the operation
            $this->saveMarkOrder($component, $order);
            $this->core->getOutput()->renderJsonSuccess();
        } catch (\InvalidArgumentException $e) {
            $this->core->getOutput()->renderJsonFail($e->getMessage());
        } catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }

    public function saveMarkOrder(Component $component, array $orders) {
        foreach ($component->getMarks() as $mark) {
            if (!isset($orders[$mark->getId()])) {
                throw new \InvalidArgumentException('Missing mark id in order array');
            }
            $order = $orders[$mark->getId()];
            if (!is_int($order) || $order < 0) {
                throw new \InvalidArgumentException('All order values must be non-negative integers');
            }
            $mark->setOrder(intval($order));
        }
        $this->core->getQueries()->saveComponent($component);
    }

    public function ajaxGetStudentOutput() {
        $gradeable_id = $_REQUEST['gradeable_id'] ?? '';
        $submitter_id = $_REQUEST['who_id'] ?? '';
        $version = $_REQUEST['version'] ?? '';
        $index = $_REQUEST['index'] ?? '';

        // Get the gradeable
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }

        // Get the graded gradeable
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $submitter_id);
        if ($graded_gradeable === false) {
            return;
        }

        // get the requested version
        $version_instance = $this->tryGetVersion($graded_gradeable->getAutoGradedGradeable(), $version);
        if ($version_instance === false) {
            return;
        }

        // Get the requested testcase
        $testcase = $this->tryGetTestcase($version_instance, $index);
        if ($testcase === false) {
            return;
        }

        // Check access
        if (!$this->core->getAccess()->canI("autograding.load_checks", ["gradeable" => $graded_gradeable])) {
            // TODO: streamline permission error strings
            $this->core->getOutput()->renderJsonFail('You have insufficient permissions to access this command');
        }

        try {
            //display hidden testcases only if the user can view the entirety of this gradeable.
            $can_view_hidden = $this->core->getAccess()->canI("autograding.show_hidden_cases", ["gradeable" => $graded_gradeable]);
            $popup_css = "{$this->core->getConfig()->getBaseUrl()}css/diff-viewer.css";
            $this->core->getOutput()->renderJsonSuccess(
                $this->core->getOutput()->renderTemplate('AutoGrading', 'loadAutoChecks',
                    $graded_gradeable, $version_instance, $testcase, $popup_css, $submitter_id, $can_view_hidden)
            );
        } catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }

    public function addOneMark() {
        $gradeable_id = $_POST['gradeable_id'];
        $user_id = $this->core->getQueries()->getUserFromAnon($_POST['anon_id'])[$_POST['anon_id']];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $user_id);

        if (!$this->core->getAccess()->canI("grading.electronic.add_one_new_mark", ["gradeable" => $gradeable])) {
            $response = array('status' => 'failure');
            $this->core->getOutput()->renderJson($response);
            return;
        }

        $note = $_POST['note'];
        $points = $_POST['points'];
        foreach ($gradeable->getComponents() as $component) {
            if(is_array($component)) {
                if($component[0]->getId() != $_POST['gradeable_component_id']) {
                    continue;
                }
            } else if ($component->getId() != $_POST['gradeable_component_id']) {
                continue;
            }
            $order_counter = $this->core->getQueries()->getGreatestGradeableComponentMarkOrder($component);
            $order_counter++;
            $mark = new GradeableComponentMark($this->core);
            $mark->setGcId($component->getId());
            $mark->setPoints($points);
            $mark->setNote($note);
            $mark->setOrder($order_counter);
            $id = $mark->create();

            $marks=$component->getMarks();
            array_push($marks, $mark);
            $component->setMarks($marks);

            $response = ["id" => $id];
            $this->core->getOutput()->renderJson($response);
            return;
        }
        $this->core->getOutput()->renderJson(["status" => "failure"]);
        return;
    }
    public function deleteOneMark() {
        $gradeable_id = $_POST['gradeable_id'];
        $user_id = $this->core->getQueries()->getUserFromAnon($_POST['anon_id'])[$_POST['anon_id']];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $user_id);

        if (!$this->core->getAccess()->canI("grading.electronic.delete_one_mark", ["gradeable" => $gradeable])) {
            $response = array('status' => 'failure');
            $this->core->getOutput()->renderJson($response);
            return;
        }

        $gcm_id = $_POST['gradeable_component_mark_id'];
        foreach ($gradeable->getComponents() as $component) {
            if ($component->getId() != $_POST['gradeable_component_id']) {
                continue;
            } else {
                foreach ($component->getMarks() as $mark) {
                    if ($mark->getId() == $gcm_id) {
                        $this->core->getQueries()->deleteGradeableComponentMark($mark);
                        $this->core->getOutput()->renderJson(["status" => "success"]);
                        return;
                    }
                }
            }
        }
        $this->core->getOutput()->renderJson(["status" => "failure"]);
        return;
    }
    public function saveGeneralComment() {
        $gradeable_id = $_POST['gradeable_id'] ?? '';
        $anon_id = $_POST['anon_id'] ?? '';
        $comment = $_POST['gradeable_comment'] ?? '';

        // Get the gradeable
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }

        // Get user id from the anon id
        $user_id = $this->tryGetUserIdFromAnonId($anon_id);
        if ($user_id === false) {
            return;
        }

        // Get the graded gradeable
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $user_id);
        if ($graded_gradeable === false) {
            return;
        }

        // Check access
        if (!$this->core->getAccess()->canI("grading.electronic.save_general_comment", ["gradeable" => $graded_gradeable])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to save component general comment');
            return;
        }

        // Get the Ta graded gradeable
        $ta_graded_gradeable = $graded_gradeable->getOrCreateTaGradedGradeable();

        // Set the comment
        $ta_graded_gradeable->setOverallComment($comment);

        // New info, so reset the user viewed date
        $ta_graded_gradeable->resetUserViewedDate();

        // Finally, save the graded gradeable
        $this->core->getQueries()->saveTaGradedGradeable($ta_graded_gradeable);

        $this->core->getOutput()->renderJsonSuccess();
    }

    protected function ajaxGetGradedComponent() {
        $gradeable_id = $_GET['gradeable_id'] ?? '';
        $anon_id = $_GET['anon_id'] ?? '';
        $component_id = $_GET['component_id'] ?? '';

        $grader = $this->core->getUser();

        // Get the gradeable
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }

        // get the component
        $component = $this->tryGetComponent($gradeable, $component_id);
        if ($component === false) {
            return;
        }

        // Get user id from the anon id
        $user_id = $this->tryGetUserIdFromAnonId($anon_id);
        if ($user_id === false) {
            return;
        }

        // Get the graded gradeable
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $user_id);
        if ($graded_gradeable === false) {
            return;
        }

        // checks if user has permission
        if (!$this->core->getAccess()->canI("grading.electronic.get_mark_data", ["gradeable" => $graded_gradeable, "component" => $component])) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to get component data');
            return;
        }

        // Get / create the TA grade
        $ta_graded_gradeable = $graded_gradeable->getOrCreateTaGradedGradeable();

        // Get / create the graded component
        $graded_component = $ta_graded_gradeable->getOrCreateGradedComponent($component, $grader, true);

        try {
            // Once we've parsed the inputs and checked permissions, perform the operation
            $details = $this->getGradedComponent($graded_component);
            $this->core->getOutput()->renderJsonSuccess($details);
        } catch (\InvalidArgumentException $e) {
            $this->core->getOutput()->renderJsonFail($e->getMessage());
        } catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }

    protected function getGradedComponent(GradedComponent $graded_component) {
        $component_data = [];
        $mark_data = [];
        foreach ($graded_component->getComponent()->getMarks() as $mark) {
            $data = $mark->toArray();
            $data['has_mark'] = $graded_component->hasMark($mark);
            $mark_data[] = $data;
        }
        $component_data['marks'] = $mark_data;
        $component_data['score'] = $graded_component->getScore();
        $component_data['comment'] = $graded_component->getComment();
        return $component_data;
    }

    public function getGradeableComment() {
        $gradeable_id = $_POST['gradeable_id'];
        $user_id = $this->core->getQueries()->getUserFromAnon($_POST['anon_id'])[$_POST['anon_id']];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $user_id);

        if (!$this->core->getAccess()->canI("grading.electronic.get_gradeable_comment", ["gradeable" => $gradeable])) {
            $response = array('status' => 'failure');
            $this->core->getOutput()->renderJson($response);
            return $response;
        }

        $response = array('status' => 'success', 'data' => $gradeable->getOverallComment());
        $this->core->getOutput()->renderJson($response);
        return $response;
    }

    public function getUsersThatGotTheMark() {
        $gradeable_id = $_POST['gradeable_id'];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id);
        $gcm_id = $_POST['gradeable_component_mark_id'];
        if (!$this->core->getAccess()->canI("grading.electronic.get_marked_users", ["gradeable" => $gradeable])) {
            $response = array('status' => 'failure');
            $this->core->getOutput()->renderJson($response);
            return $response;
        }

        $return_data = [];
        $name_info = [];
        foreach ($gradeable->getComponents() as $component) {
            if ($component->getId() != $_POST['gradeable_component_id']) {
                continue;
            } else {
                foreach ($component->getMarks() as $mark) {
                    if ($mark->getId() == $gcm_id) {
                        $return_data = $this->core->getQueries()->getUsersWhoGotMark($component->getId(), $mark, $gradeable->isTeamAssignment());
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
            if(!$this->core->getAccess()->canI("grading.electronic.get_marked_users.full_stats")){
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
            if(!$this->core->getAccess()->canI("grading.electronic.get_marked_users.full_stats")){
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
                $graded_components = $this->core->getQueries()->getGradedComponentsCountByTeamGradingSections($gradeable_id, $sections, $section_key, true);
            }
            else {
                $total_users = $this->core->getQueries()->getTotalUserCountByGradingSections($sections, $section_key);
                $no_team_users = array();
                $graded_components = $this->core->getQueries()->getGradedComponentsCountByGradingSections($gradeable_id, $sections, $section_key, false);
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



