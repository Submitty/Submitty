<?php

namespace app\models\notebook;

use app\libraries\Core;
use app\models\AbstractModel;

/**
 * Class AbstractNotebookInput
 * @package app\models\notebook
 *
 * Information required to load gradeable input submissions on the submission page
 *
 * @method string getFileName()
 * @method array[] getImages()
 * @method string getLabel()
 */
class AbstractNotebookInput extends AbstractModel {

    /** @prop @var string The name of the file to save text box data to */
    protected $file_name;

    public function __construct(Core $core, array $details) {
        parent::__construct($core);

        $this->file_name = $details['filename'];
    }
}
