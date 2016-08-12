<?php

namespace app\controllers;

use app\controllers\IController;
use app\libraries\Core;
use app\libraries\DateUtils;
use app\libraries\ErrorMessages;
use app\libraries\FileUtils;
use app\libraries\Logger;
use app\libraries\Utils;
use app\models\GradeableList;

class NavigationController implements IController {
    /**
     * @var Core
     */
    private $core;
    
    /**
     * @var GradeableList
     */
    private $gradeables_list;
    
    public function __construct(Core $core) {
        $this->core = $core;
        $this->gradeables_list = new GradeableList($this->core);
    }

    public function run() {
        $this->core->getOutput()->addCSS("https://fonts.googleapis.com/css?family=Open+Sans+Condensed:300,300italic,700");
        $this->core->getOutput()->addCSS("https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic,700italic");
        $this->core->getOutput()->addCSS("https://fonts.googleapis.com/css?family=PT+Sans:700,700italic");
  		$this->core->getOutput()->addCSS("https://fonts.googleapis.com/css?family=Inconsolata");
        
        $future_gradeables_list = $this->gradeables_list->getFutureGradeables();
        $open_gradeables_list = $this->gradeables_list->getOpenElectronicGradeables(false);
        $closed_gradeables_list = $this->gradeables_list->getClosedElectronicGradeables(false);
        $grading_gradeables_list = $this->gradeables_list->getGradingGradeables();
        $graded_gradeables_list = $this->gradeables_list->getGradedGradeables();
        $sections_to_lists = array("FUTURE" => $future_gradeables_list,"OPEN" => $open_gradeables_list, 
                                   "CLOSED" => $closed_gradeables_list, "ITEMS BEING GRADED" => $grading_gradeables_list,
                                   "GRADED" => $graded_gradeables_list);
        $this->core->getOutput()->renderOutput('Navigation', 'showGradeables',
                                               $sections_to_lists);  
    }
}