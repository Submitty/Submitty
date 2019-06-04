<?php

namespace app\models\gradeable;


use app\libraries\Core;
use app\models\gradeable\AbstractTextBox;

/**
 * Class SubmissionCodeBox
 * @package app\models\gradeable
 *
 * Information required to load code box submissions on the submission page
 *
 * @method string getLanguage()
 */
class SubmissionCodeBox extends AbstractTextBox {
    /** @property @var string The programming language of the text box */
    protected $language;

    public function __construct(Core $core, array $details) {
        parent::__construct($core, $details);

        $this->language = $details['programming_language'];
    }
}
