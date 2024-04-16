<?php

namespace app\models\gradeable;

use app\libraries\Core;
use app\models\AbstractModel;

/**
 * Class SloccountConfig
 * @package app\models\gradeable
 *
 * @method string getTitle()
 * @method string getDescription()
 */
class SloccountConfig extends AbstractModel {
    /** @prop
     * @var string The human readable title */
    protected $title;
    /** @prop
     * @var string The human readable description */
    protected $description;


    public function __construct(Core $core, array $details) {
        parent::__construct($core);

        // Was there actually a config file to read from
        if ($details === []) {
            throw new \InvalidArgumentException('Provided details were blank or null');
        }
        $this->title = $details["title"];
        $this->description = $details["description"] ?? "";
    }
}
