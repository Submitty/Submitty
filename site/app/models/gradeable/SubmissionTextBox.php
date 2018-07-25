<?php

namespace app\models\gradeable;


use app\libraries\Core;
use app\models\AbstractModel;

/**
 * Class SubmissionTextBox
 * @package app\models\gradeable
 *
 * Information required to load text box submissions on the submission page
 *
 * @method string getFileName()
 * @method array[] getImages()
 * @method string getLabel()
 * @method int getRowCount()
 */
class SubmissionTextBox extends AbstractModel {

    /** @property @var string The name of the file to save text box data to */
    protected $file_name;
    /** @property @var array[] An array of arrays each holding a 'height', 'name' and 'width' property for an image */
    protected $images;
    /** @property @var string The label to put above this text box */
    protected $label;
    /** @property @var int The row height for the text box */
    protected $row_count;

    public function __construct(Core $core, array $details) {
        parent::__construct($core);

        $this->file_name = $details['filename'];
        $this->label = $details['label'];
        $this->row_count = $details['rows'];

        $this->images = array_map(function ($image_details) {
            if(!isset($image_details['image_name'])) {
                throw new \InvalidArgumentException('Config image details must have image name');
            }
            return [
                'height' => $image_details['image_height'] ?? 0,
                'width' => $image_details['image_width'] ?? 0,
                'name' => $image_details['image_name']
            ];
        }, $details['images'] ?? []);
    }

    public function setFileName() {
        throw new \BadFunctionCallException('Setters disabled for SubmissionTextBox');
    }

    public function setImages() {
        throw new \BadFunctionCallException('Setters disabled for SubmissionTextBox');
    }

    public function setLabel() {
        throw new \BadFunctionCallException('Setters disabled for SubmissionTextBox');
    }

    public function setRowCount() {
        throw new \BadFunctionCallException('Setters disabled for SubmissionTextBox');
    }
}