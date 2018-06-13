<?php
namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\Core;
use app\libraries\Output;
use app\libraries\FileUtils;

class PlagiarismController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'compare':
                $this->plagiarismCompare();
                break;
            case 'index':
                $this->plagiarismIndex();
                break;
            case 'run_plagiarism':
                $this->runPlagiarismForm();
                break;    
            default:
                $this->core->getOutput()->addBreadcrumb('Plagiarism Detection');
                $this->plagiarismTree();
                break;
        }
    }

    public function plagiarismCompare() {
        $semester = $_REQUEST['semester'];
        $course = $_REQUEST['course'];
        $assignment = $_REQUEST['assignment'];
        $studenta = $_REQUEST['studenta'];
        $studentb = $_REQUEST['studentb'];
        $this->core->getOutput()->renderOutput(array('admin', 'Plagiarism'), 'plagiarismCompare', $semester, $course, $assignment, $studenta, $studentb);
    }

    public function plagiarismIndex() {
        $semester = $_REQUEST['semester'];
        $course = $_REQUEST['course'];
        $assignment = $_REQUEST['assignment'];
        $this->core->getOutput()->renderOutput(array('admin', 'Plagiarism'), 'plagiarismIndex', $semester, $course, $assignment);
    }

    public function plagiarismTree() {
        $semester = $_REQUEST['semester'];
        $course = $_REQUEST['course'];
        if (file_exists("/var/local/submitty/courses/$semester/$course/plagiarism/report/var/local/submitty/courses/$semester/$course/submissions/")) {
            $assignments = array_diff(scandir("/var/local/submitty/courses/$semester/$course/plagiarism/report/var/local/submitty/courses/$semester/$course/submissions/"), array('.', '..'));
        } else {
            $assignments = array();
        }
        $this->core->getOutput()->renderOutput(array('admin', 'Plagiarism'), 'plagiarismTree', $semester, $course, $assignments);  
    }

    public function runPlagiarismForm() {
        $semester = $_REQUEST['semester'];
        $course = $_REQUEST['course'];
        $gradeable_ids = array_diff(scandir("/var/local/submitty/courses/$semester/$course/submissions/"), array('.', '..'));
        $gradeable_ids_titles= $this->core->getQueries()->getAllGradeablesIdsAndTitles();
        foreach($gradeable_ids_titles as $i => $gradeable_id_title) {
            if(!in_array($gradeable_id_title['g_id'], $gradeable_ids)) {
                unset($gradeable_ids_titles[$i]);
            }
        }
        $all_sem_gradeables = FileUtils::getAllSemesterGradeables();

        $this->core->getOutput()->renderOutput(array('admin', 'Plagiarism'), 'runPlagiarismForm', $gradeable_ids_titles, $all_sem_gradeables);
    }
}
