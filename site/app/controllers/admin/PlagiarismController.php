<?php
namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\Core;
use app\libraries\Output;

class PlagiarismController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'compare':
                $this->plagiarismCompare();
                break;
            case 'index':
                $this->plagiarismIndex();
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
}
