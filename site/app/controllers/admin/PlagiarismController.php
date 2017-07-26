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
            default:
                $this->core->getOutput()->addBreadcrumb("Plagiarism");
                $this->plagiarismIndex();
                break;
        }
    }

    public function plagiarismIndex() {
        $this->core->getOutput()->renderOutput(array('admin', 'Plagiarism'), 'plagiarismIndex');
    }

    public function plagiarismCompare() {
        $studenta = $_REQUEST['studenta'];
        $studentb = $_REQUEST['studentb'];
        $this->core->getOutput()->renderOutput(array('admin', 'Plagiarism'), 'plagiarismCompare', $studenta, $studentb);
    }
}
