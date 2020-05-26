<?php

namespace app\models\notebook;

use app\libraries\Core;
use app\models\notebook\AbstractTextBox;

//TODO remove
class SubmissionTextBox extends AbstractTextBox {
    public function __construct(Core $core, array $details) {
        parent::__construct($core, $details);
    }
}
