<?php

namespace app\models\gradeable;


use app\libraries\Core;
use app\models\AbstractModel;

/**
 * Class AbstractGradeableInput
 * @package app\models\gradeable
 *
 * Information required to load gradeable input submissions on the submission page
 *
 * @method string getFileName()
 * @method array[] getImages()
 * @method string getLabel()
 */
class AbstractGradeableInput extends AbstractModel {

    /** @property @var string The name of the file to save text box data to */
    protected $file_name;
    /** @property @var array[] An array of arrays each holding a 'height', 'name' and 'width' property for an image */
    protected $images;
    /** @property @var string The label to put above this text box */
    protected $label;

    public function __construct(Core $core, array $details) {
        parent::__construct($core);

        $this->file_name = $details['filename'];
        $this->label = $details['label'];
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
}
