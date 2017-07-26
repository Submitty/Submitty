<?php
namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\Core;
use app\libraries\Output;

class PlagiarismController extends AbstractController {
    public function run() {
        $this->core->getOutput()->addBreadcrumb("Plagiarism");
        $this->plagiarismIndex();
    }

    public function plagiarismIndex() {
        $this->core->getOutput()->renderOutput(array('admin', 'Plagiarism'), 'plagiarismIndex');
    }
}
