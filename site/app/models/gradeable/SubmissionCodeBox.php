<?php

namespace app\models\gradeable;

use app\libraries\Core;
use app\libraries\Utils;
use app\models\gradeable\AbstractTextBox;

/**
 * Information required to load code box submissions on the submission page
 *
 * @method string getLanguage()
 * @method string getCodeMirrorMode()
 */
class SubmissionCodeBox extends AbstractTextBox {
    /** @prop @var string The programming language of the text box */
    protected $language;

    /** @prop @var string The mode to use for code mirror */
    protected $codeMirrorMode;

    public function __construct(Core $core, array $details) {
        parent::__construct($core, $details);

        $this->language = $details['programming_language'];
        $this->codeMirrorMode = $details['codemirror_mode'] ?? Utils::getCodeMirrorMode($this->language);
    }
}
