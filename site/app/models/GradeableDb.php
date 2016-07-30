<?php

namespace app\models;

use \app\libraries\Core;

/**
 * Class GradeableDb
 *
 * Populates the Gradeable model by loading the data from the database
 */
class GradeableDb extends Gradeable {
    public function __construct(Core $core, $id) {
        parent::__construct($core, $id);
    }
    
}