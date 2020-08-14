<?php

namespace app\models\notebook;

use app\libraries\CodeMirrorUtils;
use app\libraries\Core;
use app\models\notebook\AbstractTextBox;

/**
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
        $this->codeMirrorMode = $details['codemirror_mode'] ?? CodeMirrorUtils::getCodeMirrorMode($this->language);
    }
}
