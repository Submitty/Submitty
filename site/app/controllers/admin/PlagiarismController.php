
<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\Core;
use app\libraries\Output;

class PlagiarismController extends AbstractController {
    public function run() {
        $this->core->getOutput()->addBreadcrumb("Plagiarism");
        $this->plagiarismIndex();
        break;
    }

    public function listStudents() {
        $this->core->getOutput()->renderOutput(array('admin', 'Plagiarism'), 'plagiarismIndex');
    }
}
