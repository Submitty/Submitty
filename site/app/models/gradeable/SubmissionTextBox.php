<?php

namespace app\models\gradeable;


use app\libraries\Core;
use app\models\gradeable\AbstractTextBox;

/**
 * Class SubmissionTextBox
 * @package app\models\gradeable
 */
class SubmissionTextBox extends AbstractTextBox {
    public function __construct(Core $core, array $details) {
        parent::__construct($core, $details);
    }
}
