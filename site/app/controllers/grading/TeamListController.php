<?php

namespace app\controllers\grading;

use app\controllers\AbstractController;
use app\libraries\Core;
use app\libraries\GradeableType;
use app\models\GradeableList;

class TeamListController extends AbstractController {
    /** @var \app\models\GradeableList */
    private $gradeables_list;
    public function __construct(Core $core) {
        parent::__construct($core);
        $this->gradeables_list = $this->core->loadModel(GradeableList::class);
    }

    public function run() {
        if (!$this->core->getUser()->accessGrading()) {
            $this->core->getOutput()->showError("This account is not authorized to view grading section");
        }
        $gradeable_id = (isset($_REQUEST['gradeable_id'])) ? $_REQUEST['gradeable_id'] : null;
        $gradeable = $this->gradeables_list->getGradeable($gradeable_id, GradeableType::ELECTRONIC_FILE);
        $teams = $this->core->getQueries()->getTeamsByGradeableId($gradeable_id);
        $this->core->getOutput()->renderOutput(array('grading', 'TeamList'), 'showTeamList', $gradeable, $teams);
    }
}
