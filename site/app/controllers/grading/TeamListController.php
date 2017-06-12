<?php

namespace app\controllers\grading;

use app\controllers\AbstractController;
use app\libraries\database\DatabaseQueriesPostgresql;
use app\libraries\Core;
use app\libraries\GradeableType;

class TeamListController extends AbstractController {
    public function __construct(Core $core) {
        parent::__construct($core);
        $this->gradeables_list = $this->core->loadModel("GradeableList", $this->core);
    }

    public function run() {
        $gradeable_id = (isset($_REQUEST['gradeable_id'])) ? $_REQUEST['gradeable_id'] : null;
        $gradeable = $this->gradeables_list->getGradeable($gradeable_id, GradeableType::ELECTRONIC_FILE);
        $teams = $this->core->getQueries()->getTeamsByGradeableId($gradeable_id);
        $this->core->getOutput()->renderOutput(array('grading', 'TeamList'), 'showTeamList', $gradeable, $teams);
    }
}
