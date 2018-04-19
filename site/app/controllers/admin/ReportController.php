<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\Core;
use app\libraries\FileUtils;
use app\libraries\GradeableType;
use app\libraries\Output;
use app\models\Gradeable;
use app\models\HWReport;
use app\models\GradeSummary;
use app\models\LateDaysCalculation;

/*
use app\report\HWReportView;
use app\report\CSVReportView;
use app\report\GradeSummaryView;
*/
class ReportController extends AbstractController {
    public function run() {
        switch($_REQUEST['action']) {
            case 'csv':
                $this->generateCSVReport();
                break;
            case 'summary':
                $this->generateGradeSummaries();
                break;
            case 'hwreport':
                $this->generateHWReports();
                break;
            case 'reportpage':
            default:
                $this->showReportPage();
                break;
        }
    }
    
    public function showReportPage() {
        $this->core->getOutput()->renderOutput(array('admin', 'Report'), 'showReportUpdates');
    }
    
    public function generateCSVReport() {
        $students = $this->core->getQueries()->getAllUsers();
        $student_ids = array_map(function($stu) {return $stu->getId();}, $students);
        $gradeables = $this->core->getQueries()->getGradeables(null, $student_ids);
        $results = array();
        $results['header_model'] = array('First' => 'First Name', 'Last'=> 'Last Name', 'reg_section' => 'Registration Section');
        $ldu = new LateDaysCalculation($this->core);
        foreach($gradeables as $gradeable) {
            $student_id = $gradeable->getUser()->getId();
            if(!isset($results[$student_id])) {
                $results[$student_id] = array('First'=>$gradeable->getUser()->getDisplayedFirstName(), 'Last' => $gradeable->getUser()->getLastName(), 'reg_section' => $gradeable->getUser()->getRegistrationSection());
            }
            $g_id = $gradeable->getId();
            $is_electronic_gradeable = ($gradeable->getType() == GradeableType::ELECTRONIC_FILE);
            $use_ta_grading = !$is_electronic_gradeable || $gradeable->useTAGrading();

            if(!isset($results['header_model'][$g_id])) {
              $max = 0;
              if ($is_electronic_gradeable) {
                $max = $max + $gradeable->getTotalAutograderNonExtraCreditPoints();
              }
              if ($use_ta_grading) {
                $max = $max + $gradeable->getTotalTANonExtraCreditPoints();
              }
              $results['header_model'][$g_id] = $g_id.": ".$max;
            }

            $total_score = 0;
            if ($is_electronic_gradeable) {
              $total_score = $total_score + $gradeable->getGradedAutograderPoints();
            }
            if ($use_ta_grading) {
              $total_score = $total_score + $gradeable->getGradedTAPoints();
            }
            
            $late_days = $ldu->getGradeable($gradeable->getUser()->getId(), $gradeable->getId());
            // if this assignment exceeds the allowed late day policy or
            // if the student has switched versions after the ta graded,
            // then they should receive an automatic zero for this gradeable
            if( $is_electronic_gradeable &&
                ( (array_key_exists('status',$late_days) && substr($late_days['status'], 0, 3) == 'Bad') ||
                  ($use_ta_grading && !$gradeable->validateVersions()))) {
              $total_score = 0;
            }

            $results[$student_id][$g_id] = $total_score;
        }
        
        $nl = "\n";
        $csv_output = "";
        $filename = $this->core->getConfig()->getCourse()."CSVReport.csv";
        foreach($results as $id => $student) {
            $student_line = array();
            if($id === 'header_model') {
                $student_line[] = "UserId";
            }
            else {
                $student_line[] = $id;
            }
            $student_line[] = $student['First'];
            $student_line[] = $student['Last'];
            $student_line[] = $student['reg_section'];
            foreach($results['header_model'] as $grade_id => $grade) {
                if($grade_id == 'First' || $grade_id == 'Last' || $grade_id == 'reg_section') {
                    continue;
                }
                $student_line[] = $student[$grade_id];
            }
            $csv_output .= implode(",",$student_line).$nl;
        }
        $this->core->getOutput()->renderFile($csv_output, $filename);
        return $csv_output;
    }
    
    public function generateGradeSummaries() {
        $base_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'reports', 'all_grades');
        $current_user = null;
        $total_late_used = 0;
        $user = [];
        $order_by = [
            'g.g_gradeable_type',
            'CASE WHEN eg.eg_submission_due_date IS NOT NULL THEN eg.eg_submission_due_date ELSE g.g_grade_released_date END'
        ];
        foreach ($this->core->getQueries()->getGradeablesIterator(null, true, 'registration_section', 'u.user_id', null, $order_by) as $gradeable) {
            /** @var \app\models\Gradeable $gradeable */
            if ($current_user !== $gradeable->getUser()->getId()) {
                if ($current_user !== null) {
                    file_put_contents(FileUtils::joinPaths($base_path, $current_user.'_summary.json'), FileUtils::encodeJson($user));
                }
                $current_user = $gradeable->getUser()->getId();
                $user = [];
                $user['user_id'] = $gradeable->getUser()->getId();
                $user['legal_first_name'] = $gradeable->getUser()->getFirstName();
                $user['preferred_first_name'] = $gradeable->getUser()->getPreferredFirstName();
                $user['last_name'] = $gradeable->getUser()->getLastName();
                $user['registration_section'] = $gradeable->getUser()->getRegistrationSection();
                $user['default_allowed_late_days'] = $this->core->getConfig()->getDefaultStudentLateDays();
                $user['last_update'] = date("l, F j, Y");
                $total_late_used = 0;
            }
            $bucket = ucwords($gradeable->getBucket());
            if (!isset($user[$bucket])) {
                $user[$bucket] = [];
            }

            $autograding_score = $gradeable->getGradedAutoGraderPoints();
            $ta_grading_score = $gradeable->getGradedTAPoints();

            $entry = [
                'id' => $gradeable->getId(),
                'name' => $gradeable->getName(),
                'grade_released_date' => $gradeable->getGradeReleasedDate()->format('Y-m-d H:i:s O'),
            ];

            if ($gradeable->validateVersions() || !$gradeable->useTAGrading()) {
               $entry['score'] = max(0,floatval($autograding_score) + floatval($ta_grading_score));
            }
            else {
                $entry['score'] = 0;
                if ($gradeable->validateVersions(-1)) {
                    $entry['note'] = 'This has not been graded yet.';
                }
                elseif ($gradeable->getActiveVersion() !== 0) {
                    $entry['note'] = 'Score is set to 0 because there are version conflicts.';
                }
            }

            if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
                $this->addLateDays($gradeable, $entry, $total_late_used);
            }

            $entry['components'] = [];
            foreach ($gradeable->getComponents() as $component) {
                $inner = ['title' => $component->getTitle()];
                if (!$component->getIsText()) {
                    $inner['score'] = $component->getScore();
                }
                $inner['comment'] = $component->getComment();
                if ($component->getHasMarks()) {
                    $marks = [];
                    foreach ($component->getMarks() as $mark) {
                        $marks[] = [
                            'points' => $mark->getPoints(),
                            'note' => $mark->getNote()
                        ];
                    }
                    $inner['marks'] = $marks;
                }
                $entry['components'][] = $inner;
            }

            $user[$bucket][] = $entry;
        }

        file_put_contents(FileUtils::joinPaths($base_path, $current_user.'_summary.json'), FileUtils::encodeJson($user));
        $this->core->addSuccessMessage("Successfully Generated Grade Summaries");
        $this->core->getOutput()->renderOutput(array('admin', 'Report'), 'showReportUpdates');
    }

    private function addLateDays(Gradeable $gradeable, &$entry, &$total_late_used) {
        $late_days_used = $gradeable->getLateDays() - $gradeable->getLateDayExceptions();
        $status = 'Good';
        $late_flag = false;

        if ($late_days_used > 0) {
            $status = "Late";
            $late_flag = true;
        }
        //If late days used - extensions applied > allowed per assignment then status is "Bad..."
        if ($late_days_used > $gradeable->getAllowedLateDays()) {
            $status = "Bad";
            $late_flag = false;
        }
        // If late days used - extensions applied > allowed per term then status is "Bad..."
        // Do a max(0, ...) to protect against the case where the student's late days goes down
        // during the semester and they've already used late days
        if ($late_days_used > max(0, $gradeable->getStudentAllowedLateDays() - $total_late_used)) {
            $status = "Bad";
            $late_flag = false;
        }

        //A submission cannot be late and bad simultaneously. If it's late calculate late days charged. Cannot
        //be less than 0 in cases of excess extensions. Decrement remaining late days.
        if ($late_flag) {
            $curr_late_charged = $late_days_used;
            $curr_late_charged = ($curr_late_charged < 0) ? 0 : $curr_late_charged;
            $total_late_used += $curr_late_charged;
        }

        if($status === 'Bad') {
            $entry["score"] = 0;
        }
        $entry['status'] = $status;

        if ($late_flag && $late_days_used > 0) {

            // TODO:  DEPRECATE THIS FIELD
            $entry['days_late'] = $late_days_used;

            // REPLACED BY:
            $entry['days_after_deadline'] = $gradeable->getLateDays();
            $entry['extensions'] = $gradeable->getLateDayExceptions();
            $entry['days_charged'] = $late_days_used;
        }
        else {
            $entry['days_late'] = 0;
        }
    }
    
    public function generateHWReports() {
        $hw_report = new HWReport($this->core);
        $hw_report->generateAllReports();
        $this->core->addSuccessMessage("Successfully Generated HWReports");
        $this->core->getOutput()->renderOutput(array('admin', 'Report'), 'showReportUpdates');
    }
}

