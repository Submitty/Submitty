<?php

namespace app\models\notebook;

use app\libraries\CodeMirrorUtils;
use app\libraries\Core;

/**
 * @method string getLanguage()
 * @method string getCodeMirrorMode()
 * @method int getRowCount()
 */
class SubmissionCodeBox extends AbstractNotebookInput {

    /** @prop @var string The programming language of the text box */
    protected $language;

    /** @prop @var string The mode to use for code mirror */
    protected $codeMirrorMode;

    /** @prop @var int The row height for the text box */
    protected $row_count;


    public function __construct(Core $core, array $details) {
        parent::__construct($core, $details);

        $this->language = $details['programming_language'] ?? null;
        $this->codeMirrorMode = $details['codemirror_mode'] ?? CodeMirrorUtils::getCodeMirrorMode($this->language);
        $this->row_count = $details['rows'] ?? 0;
    }
}
