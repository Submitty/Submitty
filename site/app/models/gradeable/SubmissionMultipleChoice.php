<?php

namespace app\models\gradeable;

use app\libraries\Core;
use app\models\gradeable\AbstractGradeableInput;

/**
 * Class SubmissionMultipleChoice
 * @package app\models\gradeable
 *
 * Information required to load multiple choice submissions on the submission page
 *
 * @method bool getAllowsMultiple()
 * @method array getChoices()
 */
class SubmissionMultipleChoice extends AbstractGradeableInput {

    /** @property @var bool Whether or not the MC is multiselect */
    protected $allow_multiple;
    /** @property @var array The collection of options for the MC */
    protected $choices;

    public function __construct(Core $core, array $details) {
        parent::__construct($core, $details);

        if ($details["allow_multiple"] == true) {
            $this->allow_multiple = true;
        } else {
            $this->allow_multiple = false;
        }

        $this->choices = $details["choices"];
    }
}
