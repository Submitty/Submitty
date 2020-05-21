<?php

namespace app\models\gradeable;

use app\libraries\Core;
use app\models\gradeable\AbstractGradeableModel;

/**
 * Class AbstractTextBox
 * @package app\models\gradeable
 *
 * Information required to load text box submissions on the submission page
 *
 * @method int getRowCount()
 * @method bool getIsCodebox()
 */
class AbstractTextBox extends AbstractGradeableInput {

    /** @prop @var int The row height for the text box */
    protected $row_count;
    /** @prop @var bool Whether or not this text box is a code box or not */
    protected $is_codebox;

    public function __construct(Core $core, array $details) {
        parent::__construct($core, $details);

        //use value given by config or 2 - the HTML textarea default
        $this->row_count = $details['rows'] ?? 2;
        if ($details['type'] === "codebox") {
            $this->is_codebox = true;
        }
        else {
            $this->is_codebox = false;
        }
    }
}
